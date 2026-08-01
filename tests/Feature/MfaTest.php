<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/** Feature tests for Self-service MFA management. */
#[TestDox('Self-service MFA management')]
class MfaTest extends TestCase
{
    use RefreshDatabase;

    /** A valid TOTP code for the secret, computed like an authenticator app. */
    private function otpFor(User $user): string
    {
        return (new Google2FA)->getCurrentOtp(Crypt::decryptString($user->fresh()->two_factor_secret));
    }

    #[Test]
    #[TestDox('the MFA management page renders before enrollment')]
    public function management_page_renders(): void
    {
        $this->actingAs(User::factory()->create())->get(route('two-factor.show'))->assertOk();
    }

    #[Test]
    #[TestDox('a user can begin enrollment and then confirm it with a valid code')]
    public function user_can_enable_and_confirm(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('two-factor.enable'))->assertRedirect(route('two-factor.show'));
        $this->assertNotNull($user->fresh()->two_factor_secret);

        $this->actingAs($user)->post(route('two-factor.confirm'), ['code' => $this->otpFor($user)])
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    #[Test]
    #[TestDox('confirming with an invalid code is rejected')]
    public function confirm_rejects_invalid_code(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('two-factor.enable'));

        $this->actingAs($user)->post(route('two-factor.confirm'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    #[Test]
    #[TestDox('the management page shows recovery codes once MFA is enabled')]
    public function management_page_shows_recovery_codes(): void
    {
        $user = User::factory()->create();
        $service = app(TwoFactorService::class);
        $service->enable($user);
        $service->confirm($user, $this->otpFor($user));

        $code = $service->recoveryCodes($user->fresh())[0];

        $this->actingAs($user->fresh())->get(route('two-factor.show'))->assertOk()->assertSee($code);
    }

    #[Test]
    #[TestDox('the enrollment QR is embedded inline, never fetched from a third party')]
    public function enrollment_qr_is_embedded_inline(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('two-factor.enable'))
            ->assertRedirect(route('two-factor.show'));

        $response = $this->actingAs($user)->get(route('two-factor.show'))->assertOk();

        $response->assertSee('src="data:image/svg+xml;base64,', false);
        $response->assertDontSee('qrserver');
        $response->assertDontSee('otpauth://', false);
    }

    #[Test]
    #[TestDox('a user can disable MFA')]
    public function user_can_disable(): void
    {
        $user = User::factory()->create();
        $service = app(TwoFactorService::class);
        $service->enable($user);
        $service->confirm($user, $this->otpFor($user));
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        $this->actingAs($user->fresh())->delete(route('two-factor.destroy'))->assertRedirect(route('two-factor.show'));

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }
}
