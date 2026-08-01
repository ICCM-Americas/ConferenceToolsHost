<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/** Feature tests for Passkeys. */
#[TestDox('Passkeys')]
class PasskeyTest extends TestCase
{
    use RefreshDatabase;

    /** A stored passkey row for the user. */
    private function passkeyFor(User $user, string $name, string $credentialId): Passkey
    {
        return Passkey::forceCreate([
            'user_id' => $user->id,
            'name' => $name,
            'credential_id' => $credentialId,
            'credential' => ['x' => 1],
        ]);
    }

    #[Test]
    #[TestDox('guests can fetch passwordless login options')]
    public function guests_can_fetch_login_options(): void
    {
        $this->getJson(route('passkey.login-options'))
            ->assertOk()
            ->assertJsonStructure(['options' => ['challenge', 'rpId']]);
    }

    #[Test]
    #[TestDox('a user can remove their own passkey')]
    public function user_can_remove_own_passkey(): void
    {
        $user = User::factory()->create();
        $passkey = $this->passkeyFor($user, 'Laptop', 'cred-1');

        $this->actingAs($user)->delete(route('passkey.destroy', $passkey))->assertRedirect();

        $this->assertDatabaseMissing('passkeys', ['id' => $passkey->id]);
    }

    #[Test]
    #[TestDox('passkeys are listed on the profile page')]
    public function passkeys_listed_on_profile(): void
    {
        $user = User::factory()->create();
        $this->passkeyFor($user, 'My Security Key', 'cred-2');

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee('My Security Key');
    }

    /**
     * @return iterable<string, array{bool, bool}>
     */
    public static function lockStates(): iterable
    {
        yield 'unlocked user may log in' => [false, true];
        yield 'locked user may not log in' => [true, false];
    }

    #[Test]
    #[TestDox('the passkey login authorizer rejects a locked user (host authorizeLoginUsing rule)')]
    #[DataProvider('lockStates')]
    public function passkey_login_respects_account_lock(bool $locked, bool $allowed): void
    {
        $user = User::factory()->create(['is_locked' => $locked]);
        $passkey = $this->passkeyFor($user, 'Key', 'cred-lock');

        $this->assertSame($allowed, Passkeys::allowsLogin(Request::create('/'), $passkey));
    }
}
