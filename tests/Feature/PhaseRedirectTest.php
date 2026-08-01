<?php

namespace Tests\Feature;

use App\Models\User;
use ConferenceTools\BoFScheduler\Enums\EventPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * The host app owns *when* to redirect after login. It delegates the non-admin
 * destination to the scheduler package's phase logic, while keeping the admin
 * and forced-password decisions for itself.
 */
#[TestDox('Post-login phase redirection (host responsibility)')]
class PhaseRedirectTest extends TestCase
{
    use RefreshDatabase;

    /** Sign the user in through the login form. */
    private function login(User $user)
    {
        return $this->post('/login', ['email' => $user->email, 'password' => 'longenoughpassword']);
    }

    /**
     * @return iterable<string, array{EventPhase, string}>
     */
    public static function phaseRoutes(): iterable
    {
        yield 'nomination' => [EventPhase::Nomination, 'scheduler.sessions.index'];
        yield 'selection' => [EventPhase::Selection, 'scheduler.selection.index'];
        yield 'results' => [EventPhase::Results, 'scheduler.results.authenticated'];
    }

    #[Test]
    #[TestDox('a non-admin login lands on the phase-appropriate scheduler page')]
    #[DataProvider('phaseRoutes')]
    public function login_lands_on_phase_page(EventPhase $phase, string $route): void
    {
        $this->setPhase($phase);
        $user = User::factory()->create(['password' => Hash::make('longenoughpassword')]);

        $this->login($user)->assertRedirect(route($route));
    }

    #[Test]
    #[TestDox('an admin login lands on the scheduler admin dashboard')]
    public function admin_lands_on_dashboard(): void
    {
        $this->setPhase(EventPhase::Nomination);
        $admin = User::factory()->admin()->create(['password' => Hash::make('longenoughpassword')]);

        $this->login($admin)->assertRedirect(route('scheduler.admin.dashboard'));
    }

    #[Test]
    #[TestDox('a user who must change their password is sent to the forced-change screen')]
    public function must_change_password_redirects_to_forced(): void
    {
        $this->setPhase(EventPhase::Nomination);
        $user = User::factory()->create([
            'password' => Hash::make('longenoughpassword'),
            'must_change_password' => true,
        ]);

        $this->login($user)->assertRedirect(route('password.forced.edit'));
    }
}
