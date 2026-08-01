<?php

namespace App\Providers;

use App\Models\Setting;
use App\Models\User;
use ConferenceTools\BoFScheduler\BoFSchedulerServiceProvider;
use ConferenceTools\Branding\BrandingServiceProvider;
use ConferenceTools\Branding\Contracts\BrandingProvider;
use ConferenceTools\Registration\Models\GroupInvite;
use ConferenceTools\Registration\Models\HomeCardMessage;
use ConferenceTools\Registration\RegistrationServiceProvider;
use ConferenceTools\Registration\Services\RegistrationEmails;
use ConferenceTools\Registration\Services\RegistrationStatus;
use ConferenceTools\Registration\Services\VariableInterpolator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;

/** Host wiring for the optional conference-tools packages and the seams they leave to the host (branding, admin gates, factories, shared view data). */
class AppServiceProvider extends ServiceProvider
{
    /**
     * The optional conference-tools packages, by service provider class.
     * Auto-discovery is disabled for these in composer.json ("dont-discover")
     * so a deployment that ships without one — or has its directory removed
     * afterwards — does not leave a dangling provider reference in
     * bootstrap/cache/packages.php that fatals at boot. The host registers each
     * one itself instead, but only when it is actually installed.
     */
    private const OPTIONAL_PROVIDERS = [
        BoFSchedulerServiceProvider::class,
        RegistrationServiceProvider::class,
        // Branding must come last: the bof-scheduler and registration providers
        // each bind the shared BrandingProvider to their neutral DefaultBranding,
        // and the branding package's provider binds it to the real, host-facing
        // BrandingService — so it has to register after them to win.
        BrandingServiceProvider::class,
    ];

    /** Register each installed optional package provider, branding last so its BrandingProvider binding wins. */
    public function register(): void
    {
        // Register the optional packages' providers in place of auto-discovery,
        // each only when installed. Branding owns the BrandingProvider seam and
        // binds its real implementation (see the ordering note above); the other
        // packages consume that contract for the global $branding view variable.
        // (Auto-discovery used to register these packages ahead of
        // AppServiceProvider, giving the same ordering.)
        foreach (self::OPTIONAL_PROVIDERS as $provider) {
            if (self::packageInstalled($provider)) {
                $this->app->register($provider);
            }
        }
    }

    /**
     * Whether an optional conference-tools package is installed, detected by the
     * autoloadability of its service provider.
     *
     * The `@` is load-bearing. With an optimized classmap (the deploy runs
     * composer install --optimize-autoloader) a package directory that is absent
     * — or removed after deployment — still has a classmap entry pointing at a
     * now-missing file. A bare class_exists() then triggers an include() warning
     * that Laravel's error handler escalates into a fatal ErrorException;
     * suppressing the warning lets the check simply return false.
     */
    public static function packageInstalled(string $providerClass): bool
    {
        return @class_exists($providerClass);
    }

    /** Wire the host-owned seams: shared view data, the password rule, the passkey lock check, the admin gates, and the combined factory resolver. */
    public function boot(): void
    {
        // The host's own views (auth, profile, admin) extend layouts.app, which
        // renders $branding. The scheduler package only shares $branding to its
        // own namespaced views, so share it to host views here too. Resolved
        // lazily per view from the BrandingProvider singleton.
        View::composer('*', function ($view): void {
            $view->with('branding', $this->app->make(BrandingProvider::class));
        });

        // The top navigation links to a printed/external event schedule and to
        // the ICCM NAS site, whose URLs are admin-editable host settings (empty
        // hides each link). Resolve them for the layout that renders the links;
        // the "Site settings" form passes the same values itself (both go
        // through the Setting accessors).
        View::composer('layouts.app', function ($view): void {
            $view->with('scheduleUrl', Setting::scheduleUrl());
            $view->with('iccmNasUrl', Setting::iccmNasUrl());
        });

        // The conference-tools packages are optional: the app can ship with only
        // one of them installed (e.g. bof-scheduler now, registration next year).
        // Expose each package's presence to every view so controls that depend on
        // a package render only when that package is installed. A package counts
        // as installed when its service provider class is autoloadable.
        View::share('bof_schedulerInstalled', self::packageInstalled(BoFSchedulerServiceProvider::class));
        View::share('registrationInstalled', self::packageInstalled(RegistrationServiceProvider::class));

        // Whether registration is currently open to registrants (the package's
        // admin-set window; see RegistrationStatus), plus the rest of the data
        // the home dashboard's registration card needs. Resolved lazily per
        // view — it reads the database — and guarded by the shared installed
        // flag so a package-less install never touches the package classes.
        View::composer('home', function ($view): void {
            $installed = View::shared('registrationInstalled');
            $status = $installed ? $this->app->make(RegistrationStatus::class) : null;
            $open = $installed && $status->isOpen();

            $view->with('registrationOpen', $open);

            // While not open, the card shows one admin-authored message: the
            // "before open" one while a scheduled open date is still in the
            // future, otherwise the "after close" one (covers both an actual
            // close and no window being scheduled at all) — see
            // HomeCardMessage, distinct from the package's own Closed Page.
            $view->with('registrationHomeMessageBody', $installed && ! $open
                ? $this->app->make(VariableInterpolator::class)->interpolate(
                    HomeCardMessage::forKey(
                        $status->opensAt()?->isFuture()
                            ? HomeCardMessage::BEFORE_OPEN
                            : HomeCardMessage::AFTER_CLOSE
                    )->translate('body')
                )
                : null);

            $view->with('registrationAdminEmail', $installed
                ? $this->app->make(RegistrationEmails::class)->adminEmail()
                : null);

            // A group leader's not-yet-completed invitees, for the "who's in
            // your group" list — empty for anyone else.
            $view->with('registrationPendingInvites', $installed && auth()->user()?->is_group_admin
                ? GroupInvite::query()
                    ->where('group_id', auth()->user()->group_id)
                    ->whereNull('consumed_at')
                    ->get()
                : collect());
        });

        // Password rules: length-based only (min 12), no character-class
        // composition requirements. Applies everywhere Password::defaults() is used.
        Password::defaults(fn () => Password::min(12));

        // Login and the two-factor challenge have no other protection against
        // credential/OTP guessing: 5 attempts/minute, keyed by the account being
        // targeted (email, or the pending login's user id) plus IP — so a single
        // blocked account doesn't throttle everyone behind a shared IP, and
        // rotating IPs doesn't bypass the per-account limit.
        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(config('security.login_rate_limit'))
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('two-factor', fn (Request $request): Limit => Limit::perMinute(config('security.login_rate_limit'))
            ->by(($request->session()->get('login.id') ?? 'none').'|'.$request->ip()));

        // Passkey logins must respect account locks: a locked user may not log in
        // even with a valid passkey.
        Passkeys::authorizeLoginUsing(function (Request $request, $user, Passkey $passkey): bool {
            return ! $user->is_locked;
        });

        // The scheduler package leaves "who may administer scheduling" entirely to
        // the host application. Here that means: any admin user. Hosts installing
        // the package elsewhere define this gate however they like.
        Gate::define('manage-scheduler', fn (User $user) => $user->isAdmin());
        Gate::define('manage-registration', fn (User $user) => $user->isAdmin());

        // The branding package leaves "who may administer branding" to the host;
        // here, any admin user (matches the scheduler/registration gates above).
        Gate::define('manage-branding', fn (User $user) => $user->isAdmin());

        // Both the bof-scheduler and registration packages register a global
        // Factory name resolver, and each routes models outside its own namespace
        // to the host default — so whichever boots last clobbers the other's
        // factories (e.g. Session::factory() looking for Database\Factories). As
        // the integrator, register one combined resolver after all providers have
        // booted so it wins and maps each package's models to its own factories.
        $this->app->booted(function (): void {
            Factory::guessFactoryNamesUsing(function (string $modelName): string {
                $map = [
                    'ConferenceTools\\BoFScheduler\\Models\\' => 'ConferenceTools\\BoFScheduler\\Database\\Factories\\',
                    'ConferenceTools\\Registration\\Models\\' => 'ConferenceTools\\Registration\\Database\\Factories\\',
                ];

                foreach ($map as $modelNamespace => $factoryNamespace) {
                    if (str_starts_with($modelName, $modelNamespace)) {
                        return $factoryNamespace.class_basename($modelName).'Factory';
                    }
                }

                return 'Database\\Factories\\'.class_basename($modelName).'Factory';
            });
        });
    }
}
