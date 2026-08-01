<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/** Unit tests for TwoFactorService. */
#[TestDox('TwoFactorService')]
class TwoFactorServiceTest extends TestCase
{
    use RefreshDatabase;

    /** The service under test, resolved from the container. */
    private function service(): TwoFactorService
    {
        return app(TwoFactorService::class);
    }

    /** A valid TOTP code for the secret, computed like an authenticator app. */
    private function otpFor(User $user): string
    {
        return (new Google2FA)->getCurrentOtp(Crypt::decryptString($user->fresh()->two_factor_secret));
    }

    #[Test]
    #[TestDox('generates distinct secret keys')]
    public function generates_secrets(): void
    {
        $service = $this->service();
        $this->assertNotSame($service->generateSecret(), $service->generateSecret());
    }

    #[Test]
    #[TestDox('enrollment stores an unconfirmed secret with recovery codes')]
    public function enable_stores_unconfirmed_secret(): void
    {
        $user = User::factory()->create();

        $secret = $this->service()->enable($user);

        $this->assertNotEmpty($secret);
        $this->assertNotNull($user->fresh()->two_factor_secret);
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        $this->assertCount(8, $this->service()->recoveryCodes($user->fresh()));
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    #[Test]
    #[TestDox('confirmation succeeds with a valid code and enables the factor')]
    public function confirm_succeeds_with_valid_code(): void
    {
        $user = User::factory()->create();
        $this->service()->enable($user);

        $this->assertTrue($this->service()->confirm($user, $this->otpFor($user)));
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    #[Test]
    #[TestDox('confirmation fails for a user with no secret')]
    public function confirm_fails_without_secret(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->service()->confirm($user, '123456'));
    }

    #[Test]
    #[TestDox('confirmation fails for an invalid code')]
    public function confirm_fails_for_invalid_code(): void
    {
        $user = User::factory()->create();
        $this->service()->enable($user);

        $this->assertFalse($this->service()->confirm($user, '000000'));
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    #[Test]
    #[TestDox('verify returns false when the user has no secret')]
    public function verify_false_without_secret(): void
    {
        $this->assertFalse($this->service()->verify(User::factory()->create(), '123456'));
    }

    #[Test]
    #[TestDox('a valid recovery code is accepted once and then consumed')]
    public function recovery_code_is_consumed_once(): void
    {
        $user = User::factory()->create();
        $this->service()->enable($user);
        $code = $this->service()->recoveryCodes($user->fresh())[0];

        $this->assertTrue($this->service()->useRecoveryCode($user, $code));
        // Second use fails; the code is gone.
        $this->assertFalse($this->service()->useRecoveryCode($user->fresh(), $code));
        $this->assertCount(7, $this->service()->recoveryCodes($user->fresh()));
    }

    #[Test]
    #[TestDox('an unknown recovery code is rejected')]
    public function unknown_recovery_code_rejected(): void
    {
        $user = User::factory()->create();
        $this->service()->enable($user);

        $this->assertFalse($this->service()->useRecoveryCode($user, 'not-a-real-code'));
    }

    #[Test]
    #[TestDox('recovery codes are empty when none are stored')]
    public function recovery_codes_empty_without_storage(): void
    {
        $this->assertSame([], $this->service()->recoveryCodes(User::factory()->create()));
    }

    #[Test]
    #[TestDox('recovery codes are empty when the stored value is not a JSON array')]
    public function recovery_codes_empty_when_stored_value_is_not_an_array(): void
    {
        // A corrupt/legacy value that decrypts to a non-array (here JSON null)
        // must degrade to an empty list rather than returning null.
        $user = User::factory()->create();
        $user->forceFill(['two_factor_recovery_codes' => Crypt::encryptString('null')])->save();

        $this->assertSame([], $this->service()->recoveryCodes($user->fresh()));
    }

    #[Test]
    #[TestDox('disabling clears the secret, recovery codes and confirmation')]
    public function disable_clears_everything(): void
    {
        $user = User::factory()->create();
        $this->service()->enable($user);
        $this->service()->confirm($user, $this->otpFor($user));
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        $this->service()->disable($user);

        $user = $user->fresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
        $this->assertFalse($user->hasTwoFactorEnabled());
    }

    #[Test]
    #[TestDox('builds an otpauth QR code URL for the user')]
    public function builds_qr_code_url(): void
    {
        $user = User::factory()->create(['email' => 'qr@example.com']);
        $secret = $this->service()->enable($user);

        $url = $this->service()->qrCodeUrl($user, $secret);

        $this->assertStringContainsString('otpauth://', $url);
        $this->assertStringContainsString('qr%40example.com', $url);
    }

    #[Test]
    #[TestDox('renders the QR code locally as an inline SVG data URI')]
    public function renders_qr_code_as_inline_svg(): void
    {
        $user = User::factory()->create();
        $url = $this->service()->qrCodeUrl($user, $this->service()->enable($user));

        $image = $this->service()->qrCodeImage($url);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $image);
        $this->assertStringContainsString('<svg', base64_decode(substr($image, strlen('data:image/svg+xml;base64,'))));
    }
}
