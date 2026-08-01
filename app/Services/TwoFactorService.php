<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP-based MFA built on pragmarx/google2fa. Secrets and recovery codes are
 * stored encrypted on the user row. A factor is only "enabled" once confirmed
 * with a valid code (two_factor_confirmed_at set).
 */
class TwoFactorService
{
    /** Edge length, in pixels, of the rendered enrollment QR code. */
    private const QR_SIZE = 200;

    public function __construct(private readonly Google2FA $google2fa) {}

    /** A fresh TOTP secret for enrollment. */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * Begin enrollment: store an (unconfirmed) secret and fresh recovery codes.
     */
    public function enable(User $user): string
    {
        $secret = $this->generateSecret();

        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($this->generateRecoveryCodes())),
            'two_factor_confirmed_at' => null,
        ])->save();

        return $secret;
    }

    /**
     * Confirm enrollment with a TOTP code. Returns true on success.
     */
    public function confirm(User $user, string $code): bool
    {
        if (is_null($user->two_factor_secret)) {
            return false;
        }

        if (! $this->verify($user, $code)) {
            return false;
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return true;
    }

    /** Whether the code is a valid TOTP or unused recovery code for this user. */
    public function verify(User $user, string $code): bool
    {
        if (is_null($user->two_factor_secret)) {
            return false;
        }

        $secret = Crypt::decryptString($user->two_factor_secret);

        return $this->google2fa->verifyKey($secret, preg_replace('/\s+/', '', $code));
    }

    /**
     * Consume a one-time recovery code. Returns true if it matched.
     */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $codes = $this->recoveryCodes($user);
        $code = trim($code);

        if (! in_array($code, $codes, true)) {
            return false;
        }

        $remaining = array_values(array_filter($codes, fn ($c) => $c !== $code));
        $user->forceFill([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($remaining)),
        ])->save();

        return true;
    }

    /**
     * @return list<string>
     */
    public function recoveryCodes(User $user): array
    {
        if (is_null($user->two_factor_recovery_codes)) {
            return [];
        }

        return json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true) ?? [];
    }

    /** Clear the user's TOTP secret, recovery codes, and confirmation. */
    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /** The otpauth URL the enrollment QR code encodes. */
    public function qrCodeUrl(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );
    }

    /**
     * The otpauth URL rendered as an inline SVG data URI.
     *
     * Rendered locally and on purpose: the URL carries the shared TOTP secret,
     * so handing it to a remote QR service would disclose the second factor.
     */
    public function qrCodeImage(string $otpauthUrl): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle(self::QR_SIZE),
            new SvgImageBackEnd,
        ));

        return 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($otpauthUrl));
    }

    /**
     * @return list<string>
     */
    private function generateRecoveryCodes(): array
    {
        return Collection::times(8, fn () => Str::random(10).'-'.Str::random(10))->all();
    }
}
