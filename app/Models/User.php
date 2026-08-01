<?php

namespace App\Models;

use ConferenceTools\Registration\Concerns\InteractsWithRegistration;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;

/** A host account: password/passkey/TOTP authentication, admin and lock flags, and the registration package's registrant surface. */
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, InteractsWithRegistration, Notifiable, PasskeyAuthenticatable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_locked' => 'boolean',
            'must_change_password' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public const DISPLAY_NAME_STANDARD = 'standard';

    public const DISPLAY_NAME_LEET = 'leet';

    public const DISPLAY_NAME_RANDOM = 'random';

    /**
     * Common leet-speak substitutions, both directions: letters become numbers
     * and numbers become letters, so the conversion works whatever the mailbox
     * happens to contain.
     *
     * @var array<string, string>
     */
    private const LEET_MAP = [
        'a' => '4', 'b' => '8', 'e' => '3', 'g' => '9', 'i' => '1',
        'l' => '1', 'o' => '0', 's' => '5', 't' => '7', 'z' => '2',
        '0' => 'O', '1' => 'I', '2' => 'Z', '3' => 'E', '4' => 'A',
        '5' => 'S', '7' => 'T', '8' => 'B', '9' => 'G',
    ];

    /**
     * Create a user, generating a display name from the email when the name
     * is blank — the shared rule between self-registration and admin-side
     * user creation.
     *
     * @param  array<string, mixed>  $attributes  User::create() attributes; requires email
     */
    public static function createWithGeneratedName(array $attributes): self
    {
        if (blank($attributes['name'] ?? null)) {
            $attributes['name'] = self::generateDisplayName($attributes['email']);
        }

        // forceFill, not create(): admin-side callers pass is_admin/is_locked/
        // must_change_password, which are deliberately excluded from $fillable
        // so they can never be set via raw request input elsewhere.
        $user = new self;
        $user->forceFill($attributes);
        $user->save();

        return $user;
    }

    /**
     * Build a fallback display name from an email address, so a user who does
     * not supply one still gets a non-empty name.
     *
     * Two styles, selectable via $style:
     *   - standard: up to the first five letters of the mailbox, then five
     *     random digits.
     *   - leet: up to the first ten characters of the mailbox, leet-converted
     *     (letter<->number substitutions, other letters upper-cased), then
     *     padded with random digits to ten characters (eleven if the mailbox
     *     already supplied a full ten) as a uniqueness nudge.
     *   - random (default): randomly pick one of the two.
     *
     * The randomness is Mersenne Twister (mt_rand), deliberately NOT a
     * cryptographic PRNG: this is a best-effort uniqueness nudge, not a secret,
     * and a CSPRNG would be overkill. It does not guarantee uniqueness.
     */
    public static function generateDisplayName(string $email, string $style = self::DISPLAY_NAME_RANDOM): string
    {
        if ($style === self::DISPLAY_NAME_RANDOM) {
            $style = mt_rand(0, 1) === 0 ? self::DISPLAY_NAME_STANDARD : self::DISPLAY_NAME_LEET;
        }

        $mailbox = Str::before($email, '@');

        return $style === self::DISPLAY_NAME_LEET
            ? self::leetDisplayName($mailbox)
            : self::standardDisplayName($mailbox);
    }

    /** The standard-style fallback name: mailbox letters plus random digits. */
    private static function standardDisplayName(string $mailbox): string
    {
        $letters = Str::substr(preg_replace('/[^a-zA-Z]/', '', $mailbox) ?? '', 0, 5);
        $digits = str_pad((string) mt_rand(0, 99999), 5, '0', STR_PAD_LEFT);

        return $letters.$digits;
    }

    /** The leet-style fallback name: the mailbox leet-converted, padded with random digits. */
    private static function leetDisplayName(string $mailbox): string
    {
        $base = Str::substr(preg_replace('/[^a-zA-Z0-9]/', '', $mailbox) ?? '', 0, 10);

        // No usable characters (e.g. an all-symbol mailbox): keep a non-empty
        // name by falling back to the digit-based form.
        if ($base === '') {
            return self::standardDisplayName($mailbox);
        }

        $out = '';
        foreach (str_split(strtolower($base)) as $char) {
            $out .= self::LEET_MAP[$char] ?? strtoupper($char);
        }

        // Uniqueness nudge: each char above maps to exactly one char, so the
        // converted form is as long as $base. Pad with random digits to ten
        // characters, or to eleven when the mailbox already supplied a full ten.
        $length = strlen($base);
        $digitCount = $length < 10 ? 10 - $length : 1;
        $out .= str_pad((string) mt_rand(0, 10 ** $digitCount - 1), $digitCount, '0', STR_PAD_LEFT);

        return $out;
    }

    /** Whether this account may administer the system. */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * An "active" admin can actually sign in and act: an admin who is not
     * locked. Used by the last-active-admin safeguards.
     */
    public function isActiveAdmin(): bool
    {
        return $this->isAdmin() && ! $this->is_locked;
    }

    /** Whether TOTP enrollment is complete (secret set and confirmed). */
    public function hasTwoFactorEnabled(): bool
    {
        return ! is_null($this->two_factor_secret) && ! is_null($this->two_factor_confirmed_at);
    }
}
