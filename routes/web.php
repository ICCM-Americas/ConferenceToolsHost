<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\MigrateController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ForcedPasswordController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\CspReportController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TwoFactorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Host application routes
|--------------------------------------------------------------------------
|
| Authentication, user accounts and user administration live here in the host
| app. All scheduling routes (nomination, selection, results, admin scheduler
| screens, public projector, manifest, …) are provided by the
| conference-tools/bof-scheduler package — see config/bofscheduler.php.
*/

// Home: the signed-in user's personal dashboard (booking, profile, logout).
// Guests are bounced to login. Note this is NOT where login lands users — that
// is the phase-appropriate scheduler page (see PhaseRedirector / login).
Route::get('/', function () {
    return auth()->check()
        ? view('home')
        : redirect()->route('login');
})->name('home');

// Web app manifest ("add to home screen"). Branding/PWA is a host concern.
Route::get('/manifest.webmanifest', ManifestController::class)->name('manifest');

// CSP violation reports, posted directly by the browser per the policy's
// report-uri (see App\Http\Middleware\SecurityHeaders). Unauthenticated by
// nature; throttled so it can't become a log-flooding vector.
Route::post('/csp-report', CspReportController::class)->middleware('throttle:30,1')->name('csp.report');

/*
|--------------------------------------------------------------------------
| Guest authentication routes
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

// Two-factor challenge (between password and full login): the user is not yet
// authenticated, so this sits outside the auth middleware.
Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:two-factor');

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'not.locked'])->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Forced password change (must_change_password). Reachable even while the
    // password.current middleware would otherwise redirect here.
    Route::get('password/forced', [ForcedPasswordController::class, 'edit'])->name('password.forced.edit');
    Route::put('password/forced', [ForcedPasswordController::class, 'update'])->name('password.forced.update');

    // Everything past the forced-change gate.
    Route::middleware('password.current')->group(function () {
        // Profile.
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        // MFA management.
        Route::get('/profile/mfa', [TwoFactorController::class, 'show'])->name('two-factor.show');
        Route::post('/profile/mfa', [TwoFactorController::class, 'enable'])->name('two-factor.enable');
        Route::post('/profile/mfa/confirm', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
        Route::delete('/profile/mfa', [TwoFactorController::class, 'destroy'])->name('two-factor.destroy');

        // Host admin area (host-owned; NOT part of the scheduler package):
        // the app's own admin dashboard, branding, and user administration.
        Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

            // Host-owned site settings (the printed schedule link, etc.). Branding
            // (admin.branding.*) is a separate screen provided by the
            // conference-tools/branding package — see its README.
            Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

            // Apply any outstanding migrations in-process (the dashboard prompts
            // for this when the database is behind).
            Route::post('migrate', MigrateController::class)->name('migrate');

            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::get('users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
            Route::post('users/{user}/lock', [UserController::class, 'lock'])->name('users.lock');
            Route::post('users/{user}/unlock', [UserController::class, 'unlock'])->name('users.unlock');
            Route::post('users/{user}/make-admin', [UserController::class, 'makeAdmin'])->name('users.make-admin');
            Route::post('users/{user}/remove-admin', [UserController::class, 'removeAdmin'])->name('users.remove-admin');
            Route::post('users/{user}/trigger-password-reset', [UserController::class, 'triggerPasswordReset'])->name('users.trigger-password-reset');
            Route::post('users/{user}/set-temporary-password', [UserController::class, 'setTemporaryPassword'])->name('users.set-temporary-password');
            Route::delete('users/{user}/passkeys/{passkey}', [UserController::class, 'deletePasskey'])->name('users.passkeys.destroy');
            Route::post('users/{user}/reset-mfa', [UserController::class, 'resetMfa'])->name('users.reset-mfa');
        });
    });
});
