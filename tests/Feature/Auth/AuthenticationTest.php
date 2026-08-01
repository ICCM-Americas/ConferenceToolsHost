<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use ConferenceTools\BoFScheduler\Enums\EventPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/** Feature tests for Authentication. */
#[TestDox('Authentication')]
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setPhase(EventPhase::Nomination);
    }

    #[Test]
    #[TestDox('the login screen renders')]
    public function login_screen_renders(): void
    {
        $this->get(route('login'))->assertOk();
    }

    #[Test]
    #[TestDox('users can log in with a correct password')]
    public function users_can_log_in(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correcthorsebattery')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'correcthorsebattery'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    #[TestDox('users cannot log in with a wrong password')]
    public function wrong_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correcthorsebattery')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    #[TestDox('login for an unknown email is rejected')]
    public function unknown_email_is_rejected(): void
    {
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'whatever12345'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    #[TestDox('locked users cannot log in')]
    public function locked_users_cannot_log_in(): void
    {
        $user = User::factory()->locked()->create(['password' => Hash::make('correcthorsebattery')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'correcthorsebattery'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    #[TestDox('users can log out')]
    public function users_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('scheduler.results.public'));
        $this->assertGuest();
    }

    #[Test]
    #[TestDox('a user locked mid-session is logged out on the next request')]
    public function locked_mid_session_is_logged_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->forceFill(['is_locked' => true])->save();

        $this->get('/bofs/sessions')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    #[Test]
    #[TestDox('repeated failed logins for the same account are throttled after 5 attempts per minute')]
    public function repeated_failed_logins_are_throttled(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correcthorsebattery')]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
                ->assertSessionHasErrors('email')
                ->assertStatus(302);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertStatus(429);

        // The correct password is rejected too, once throttled — the limiter
        // blocks the endpoint, not just failed attempts.
        $this->post('/login', ['email' => $user->email, 'password' => 'correcthorsebattery'])
            ->assertStatus(429);
        $this->assertGuest();
    }

    #[Test]
    #[TestDox('login throttling is scoped per account, not shared across accounts on the same connection')]
    public function login_throttling_is_scoped_per_account(): void
    {
        $target = User::factory()->create(['password' => Hash::make('correcthorsebattery')]);
        $bystander = User::factory()->create(['password' => Hash::make('correcthorsebattery')]);

        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => $target->email, 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => $bystander->email, 'password' => 'correcthorsebattery'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($bystander);
    }

    #[Test]
    #[TestDox('guests are redirected from protected pages to the login screen')]
    public function guests_redirected_from_protected_pages(): void
    {
        $this->get('/bofs/sessions')->assertRedirect(route('login'));
        $this->get('/bofs/selection')->assertRedirect(route('login'));
        $this->get('/bofs/my-results')->assertRedirect(route('login'));
        $this->get('/profile')->assertRedirect(route('login'));
        $this->get('/bofs/admin')->assertRedirect(route('login'));
    }

    #[Test]
    #[TestDox('a non-admin cannot access the admin area')]
    public function non_admin_cannot_access_admin(): void
    {
        $this->actingAs(User::factory()->create())->get('/bofs/admin')->assertForbidden();
    }

    #[Test]
    #[TestDox('the home route redirects guests to login and shows authenticated users their dashboard')]
    public function home_redirects_by_auth_state(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));

        // The booking card only renders while registration is open.
        $this->openRegistration();

        $this->actingAs(User::factory()->create())->get(route('home'))
            ->assertOk()
            ->assertSee(route('registration.info'))
            ->assertSee(route('profile.edit'));
    }
}
