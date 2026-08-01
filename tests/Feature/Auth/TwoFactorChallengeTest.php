<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\TwoFactorService;
use ConferenceTools\BoFScheduler\Enums\EventPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/** Feature tests for Two-factor login challenge. */
#[TestDox('Two-factor login challenge')]
class TwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setPhase(EventPhase::Nomination);
    }

    /** A user with completed TOTP enrollment. */
    private function userWithMfa(): User
    {
        $user = User::factory()->create(['password' => Hash::make('longenoughpassword')]);
        $service = app(TwoFactorService::class);
        $secret = $service->enable($user);
        $service->confirm($user, (new Google2FA)->getCurrentOtp($secret));

        return $user->fresh();
    }

    /** A valid TOTP code for the secret, computed like an authenticator app. */
    private function otpFor(User $user): string
    {
        return (new Google2FA)->getCurrentOtp(Crypt::decryptString($user->fresh()->two_factor_secret));
    }

    #[Test]
    #[TestDox('a password login with MFA defers to the challenge instead of authenticating')]
    public function password_login_defers_to_challenge(): void
    {
        $user = $this->userWithMfa();

        $this->post('/login', ['email' => $user->email, 'password' => 'longenoughpassword'])
            ->assertRedirect(route('two-factor.challenge'));

        $this->assertGuest();
    }

    #[Test]
    #[TestDox('the challenge screen renders once a login is pending')]
    public function challenge_screen_renders_when_pending(): void
    {
        $user = $this->userWithMfa();
        $this->post('/login', ['email' => $user->email, 'password' => 'longenoughpassword']);

        $this->get(route('two-factor.challenge'))->assertOk();
    }

    #[Test]
    #[TestDox('the challenge screen redirects to login without a pending login')]
    public function challenge_redirects_without_pending(): void
    {
        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
    }

    #[Test]
    #[TestDox('submitting the challenge without a pending login redirects to login')]
    public function challenge_post_redirects_without_pending(): void
    {
        $this->post(route('two-factor.challenge'), ['code' => '123456'])->assertRedirect(route('login'));
    }

    #[Test]
    #[TestDox('a valid TOTP code completes the login')]
    public function valid_code_completes_login(): void
    {
        $user = $this->userWithMfa();
        $this->post('/login', ['email' => $user->email, 'password' => 'longenoughpassword']);

        $this->post(route('two-factor.challenge'), ['code' => $this->otpFor($user)]);

        $this->assertAuthenticatedAs($user->fresh());
    }

    #[Test]
    #[TestDox('a valid recovery code completes the login')]
    public function valid_recovery_code_completes_login(): void
    {
        $user = $this->userWithMfa();
        $recovery = app(TwoFactorService::class)->recoveryCodes($user)[0];
        $this->post('/login', ['email' => $user->email, 'password' => 'longenoughpassword']);

        $this->post(route('two-factor.challenge'), ['recovery_code' => $recovery]);

        $this->assertAuthenticatedAs($user->fresh());
    }

    #[Test]
    #[TestDox('an invalid code is rejected and the user stays unauthenticated')]
    public function invalid_code_is_rejected(): void
    {
        $user = $this->userWithMfa();
        $this->post('/login', ['email' => $user->email, 'password' => 'longenoughpassword']);

        $this->post(route('two-factor.challenge'), ['code' => '000000'])->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    #[Test]
    #[TestDox('repeated wrong codes are throttled after 5 attempts per minute')]
    public function repeated_wrong_codes_are_throttled(): void
    {
        $user = $this->userWithMfa();
        $this->post('/login', ['email' => $user->email, 'password' => 'longenoughpassword']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('two-factor.challenge'), ['code' => '000000'])
                ->assertSessionHasErrors('code');
        }

        $this->post(route('two-factor.challenge'), ['code' => '000000'])->assertStatus(429);

        // Even the correct code is rejected once throttled.
        $this->post(route('two-factor.challenge'), ['code' => $this->otpFor($user)])->assertStatus(429);
        $this->assertGuest();
    }
}
