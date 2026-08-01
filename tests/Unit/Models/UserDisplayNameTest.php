<?php

namespace Tests\Unit\Models;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/** Unit tests for User::generateDisplayName. */
#[TestDox('User::generateDisplayName')]
class UserDisplayNameTest extends TestCase
{
    #[Test]
    #[TestDox('standard style takes up to 5 mailbox letters then 5 digits')]
    public function standard_style(): void
    {
        $name = User::generateDisplayName('jane.doe@example.com', User::DISPLAY_NAME_STANDARD);

        $this->assertMatchesRegularExpression('/^janed\d{5}$/', $name);
    }

    #[Test]
    #[TestDox('standard style still produces a non-empty name when the mailbox has no letters')]
    public function standard_style_without_letters(): void
    {
        $name = User::generateDisplayName('12345@example.com', User::DISPLAY_NAME_STANDARD);

        $this->assertMatchesRegularExpression('/^\d{5}$/', $name);
    }

    #[Test]
    #[TestDox('leet style applies substitutions then pads with digits to ten characters')]
    public function leet_style(): void
    {
        // "jane.doe" -> "janedoe" (7) -> "J4N3D03" then 3 digits to reach 10.
        $this->assertMatchesRegularExpression('/^J4N3D03\d{3}$/', User::generateDisplayName('jane.doe@example.com', User::DISPLAY_NAME_LEET));
    }

    #[Test]
    #[TestDox('leet style pads a full-ten mailbox with a single digit (eleven characters)')]
    public function leet_style_full_ten(): void
    {
        // Digits convert back to letters; capped at 10 input chars, +1 digit.
        // "h4ckerlonglogin" -> first 10 "h4ckerlong" -> "HACK3R10N9" then 1 digit.
        $this->assertMatchesRegularExpression('/^HACK3R10N9\d$/', User::generateDisplayName('h4ckerlonglogin@example.com', User::DISPLAY_NAME_LEET));
    }

    #[Test]
    #[TestDox('leet style falls back to the digit form when the mailbox has no usable characters')]
    public function leet_style_without_usable_characters(): void
    {
        // An all-symbol mailbox leaves nothing to leet-convert, so it falls back
        // to the standard (digits-only) form rather than returning an empty name.
        $name = User::generateDisplayName('!!!@example.com', User::DISPLAY_NAME_LEET);

        $this->assertMatchesRegularExpression('/^\d{5}$/', $name);
    }

    #[Test]
    #[TestDox('random style only ever returns the standard or the leet form')]
    public function random_style(): void
    {
        $standard = User::generateDisplayName('jane.doe@example.com', User::DISPLAY_NAME_STANDARD);

        for ($i = 0; $i < 50; $i++) {
            $name = User::generateDisplayName('jane.doe@example.com');
            $this->assertMatchesRegularExpression('/^(janed\d{5}|J4N3D03\d{3})$/', $name, "Unexpected name: {$name}");
        }

        // Sanity: the standard helper is reachable and well-formed.
        $this->assertMatchesRegularExpression('/^janed\d{5}$/', $standard);
    }
}
