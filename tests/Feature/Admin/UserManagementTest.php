<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Laravel\Passkeys\Passkey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/** Feature tests for Admin user management. */
#[TestDox('Admin user management')]
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    /** A stored passkey row for the user. */
    private function passkeyFor(User $user, string $name = 'Phone', string $credentialId = 'cred-1'): Passkey
    {
        return Passkey::forceCreate([
            'user_id' => $user->id,
            'name' => $name,
            'credential_id' => $credentialId,
            'credential' => ['x' => 1],
        ]);
    }

    #[Test]
    #[TestDox('admin can view the create-user form')]
    public function admin_can_view_create_form(): void
    {
        $this->actingAs($this->createAdmin())->get(route('admin.users.create'))->assertOk();
    }

    #[Test]
    #[TestDox('admin can create a user with a temporary password')]
    public function admin_can_create_user(): void
    {
        $this->actingAs($this->createAdmin())->post(route('admin.users.store'), [
            'name' => 'New Nadia',
            'email' => 'nadia@example.com',
            'password' => 'longenoughpassword',
            'password_confirmation' => 'longenoughpassword',
        ])->assertRedirect();

        $user = User::where('email', 'nadia@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('New Nadia', $user->name);
        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->must_change_password);
    }

    #[Test]
    #[TestDox('creating a user without a name generates a display name from the email')]
    public function admin_create_generates_display_name(): void
    {
        $this->actingAs($this->createAdmin())->post(route('admin.users.store'), [
            'name' => '',
            'email' => 'jane.doe@example.com',
            'password' => 'longenoughpassword',
            'password_confirmation' => 'longenoughpassword',
        ])->assertRedirect();

        $name = User::where('email', 'jane.doe@example.com')->value('name');
        $this->assertMatchesRegularExpression('/^(janed\d{5}|J4N3D03\d{3})$/', $name);
    }

    #[Test]
    #[TestDox('admin can create another administrator')]
    public function admin_can_create_admin_user(): void
    {
        $this->actingAs($this->createAdmin())->post(route('admin.users.store'), [
            'email' => 'boss@example.com',
            'password' => 'longenoughpassword',
            'password_confirmation' => 'longenoughpassword',
            'is_admin' => '1',
        ])->assertRedirect();

        $this->assertTrue(User::where('email', 'boss@example.com')->first()->isAdmin());
    }

    #[Test]
    #[TestDox('creating a user requires a unique email and a confirmed password')]
    public function admin_create_is_validated(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $admin = $this->createAdmin();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'email' => 'taken@example.com',
            'password' => 'longenoughpassword',
            'password_confirmation' => 'longenoughpassword',
        ])->assertSessionHasErrors('email');

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'email' => 'fresh@example.com',
            'password' => 'longenoughpassword',
            'password_confirmation' => 'mismatchpassword',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'fresh@example.com']);
    }

    #[Test]
    #[TestDox('a non-admin cannot create a user')]
    public function non_admin_cannot_create_user(): void
    {
        $this->actingAs($this->createUser())->get(route('admin.users.create'))->assertForbidden();

        $this->actingAs($this->createUser())->post(route('admin.users.store'), [
            'email' => 'sneaky@example.com',
            'password' => 'longenoughpassword',
            'password_confirmation' => 'longenoughpassword',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    #[Test]
    #[TestDox('admin can view a user detail page')]
    public function admin_can_view_user(): void
    {
        $user = User::factory()->create(['name' => 'Detail Dan']);

        $this->actingAs($this->createAdmin())->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertSee('Detail Dan');
    }

    #[Test]
    #[TestDox('the user listing shows each user with status badges')]
    public function listing_shows_status(): void
    {
        $this->createAdmin();
        User::factory()->locked()->create(['name' => 'Locked Larry', 'email' => 'larry@example.test']);

        $this->actingAs($this->createAdmin())->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Locked Larry')
            ->assertSee('larry@example.test');
    }

    #[Test]
    #[TestDox('the user listing can be searched by name')]
    public function listing_can_be_searched(): void
    {
        $this->createAdmin();
        User::factory()->create(['name' => 'Findable Fiona']);
        User::factory()->create(['name' => 'Hidden Harry']);

        $this->actingAs($this->createAdmin())->get(route('admin.users.index', ['search' => 'Findable']))
            ->assertOk()
            ->assertSee('Findable Fiona')
            ->assertDontSee('Hidden Harry');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function filters(): iterable
    {
        yield 'admins' => ['admins'];
        yield 'locked' => ['locked'];
        yield 'must_change' => ['must_change'];
    }

    #[Test]
    #[TestDox('the user listing supports each filter')]
    #[DataProvider('filters')]
    public function listing_supports_filters(string $filter): void
    {
        $this->actingAs($this->createAdmin())->get(route('admin.users.index', ['filter' => $filter]))->assertOk();
    }

    #[Test]
    #[TestDox('admin can lock and unlock a user')]
    public function admin_can_lock_and_unlock(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.lock', $user));
        $this->assertTrue($user->fresh()->is_locked);

        $this->actingAs($admin)->post(route('admin.users.unlock', $user));
        $this->assertFalse($user->fresh()->is_locked);
    }

    #[Test]
    #[TestDox('admin can promote and demote a user')]
    public function admin_can_promote_and_demote(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.make-admin', $user));
        $this->assertTrue($user->fresh()->is_admin);

        $this->actingAs($admin)->post(route('admin.users.remove-admin', $user));
        $this->assertFalse($user->fresh()->is_admin);
    }

    #[Test]
    #[TestDox('the last active admin cannot be demoted')]
    public function last_admin_cannot_be_demoted(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->post(route('admin.users.remove-admin', $admin))->assertSessionHasErrors('admin');
        $this->assertTrue($admin->fresh()->is_admin);
    }

    #[Test]
    #[TestDox('the last active admin cannot be locked')]
    public function last_admin_cannot_be_locked(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->post(route('admin.users.lock', $admin))->assertSessionHasErrors('admin');
        $this->assertFalse($admin->fresh()->is_locked);
    }

    #[Test]
    #[TestDox('demotion is allowed when another active admin exists')]
    public function demotion_allowed_with_another_admin(): void
    {
        $admin = $this->createAdmin();
        $other = $this->createAdmin();

        $this->actingAs($admin)->post(route('admin.users.remove-admin', $other))->assertSessionHasNoErrors();
        $this->assertFalse($other->fresh()->is_admin);
    }

    #[Test]
    #[TestDox('admin can trigger a password reset email')]
    public function admin_can_trigger_password_reset(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.trigger-password-reset', $user))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    #[Test]
    #[TestDox('admin can set a temporary password forcing a change at next login')]
    public function admin_can_set_temporary_password(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create(['must_change_password' => false]);

        $this->actingAs($admin)->post(route('admin.users.set-temporary-password', $user), [
            'password' => 'temporarypass1234',
            'password_confirmation' => 'temporarypass1234',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->must_change_password);
    }

    #[Test]
    #[TestDox('admin can remove a user passkey')]
    public function admin_can_remove_passkey(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $passkey = $this->passkeyFor($user);

        $this->actingAs($admin)->delete(route('admin.users.passkeys.destroy', [$user, $passkey]));
        $this->assertDatabaseMissing('passkeys', ['id' => $passkey->id]);
    }

    #[Test]
    #[TestDox('removing a passkey belonging to another user returns 404')]
    public function removing_mismatched_passkey_404(): void
    {
        $admin = $this->createAdmin();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $passkey = $this->passkeyFor($owner);

        $this->actingAs($admin)->delete(route('admin.users.passkeys.destroy', [$other, $passkey]))->assertNotFound();
        $this->assertDatabaseHas('passkeys', ['id' => $passkey->id]);
    }

    #[Test]
    #[TestDox('admin can reset a user MFA enrollment')]
    public function admin_can_reset_mfa(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create([
            'two_factor_secret' => Crypt::encryptString('SECRET'),
            'two_factor_confirmed_at' => now(),
        ]);
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        $this->actingAs($admin)->post(route('admin.users.reset-mfa', $user));
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    #[Test]
    #[TestDox('a non-admin cannot reach user management')]
    public function non_admin_forbidden(): void
    {
        $this->actingAs($this->createUser())->get(route('admin.users.index'))->assertForbidden();
    }
}
