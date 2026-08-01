<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorService;
use ConferenceTools\BoFScheduler\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/** Feature tests for Profile management. */
#[TestDox('Profile management')]
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('the profile page renders with the passkeys section')]
    public function profile_page_renders(): void
    {
        $this->actingAs(User::factory()->create())->get(route('profile.edit'))->assertOk()->assertSee('Passkeys');
    }

    #[Test]
    #[TestDox('the profile page renders for a user with MFA enabled')]
    public function profile_page_renders_when_mfa_enabled(): void
    {
        // Exercises the edit() branch that loads recovery codes only once the
        // user has a confirmed second factor; the page then shows MFA as enabled.
        $user = User::factory()->create();
        $service = app(TwoFactorService::class);
        $service->enable($user);
        $service->confirm($user, (new Google2FA)->getCurrentOtp(Crypt::decryptString($user->fresh()->two_factor_secret)));

        $this->actingAs($user->fresh())->get(route('profile.edit'))
            ->assertOk()
            ->assertSee(__('profile.mfa_on'));
    }

    #[Test]
    #[TestDox('a user can update their display name')]
    public function user_can_update_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'New Name', 'email' => $user->email,
        ])->assertRedirect(route('profile.edit'));

        $this->assertSame('New Name', $user->fresh()->name);
    }

    #[Test]
    #[TestDox('the profile update rejects an email already taken by another user')]
    public function update_rejects_duplicate_email(): void
    {
        $other = User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Name', 'email' => 'taken@example.com',
        ])->assertSessionHasErrors('email');
    }

    #[Test]
    #[TestDox('a user can change their password with the correct current password')]
    public function user_can_change_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('oldpassword1234')]);

        $this->actingAs($user)->put(route('profile.password'), [
            'current_password' => 'oldpassword1234',
            'password' => 'newlongpassword',
            'password_confirmation' => 'newlongpassword',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('newlongpassword', $user->fresh()->password));
    }

    #[Test]
    #[TestDox('changing password requires the correct current password')]
    public function change_password_requires_current(): void
    {
        $user = User::factory()->create(['password' => Hash::make('oldpassword1234')]);

        $this->actingAs($user)->put(route('profile.password'), [
            'current_password' => 'wrongpassword',
            'password' => 'newlongpassword',
            'password_confirmation' => 'newlongpassword',
        ])->assertSessionHasErrors('current_password');
    }

    #[Test]
    #[TestDox('changing password enforces the minimum length')]
    public function change_password_requires_min_length(): void
    {
        $user = User::factory()->create(['password' => Hash::make('oldpassword1234')]);

        $this->actingAs($user)->put(route('profile.password'), [
            'current_password' => 'oldpassword1234',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }

    #[Test]
    #[TestDox('a user can delete their account, preserving their nominations')]
    public function user_can_delete_account(): void
    {
        $user = User::factory()->create(['name' => 'Gone Soon', 'password' => Hash::make('deletemepassword')]);
        $session = Session::factory()->create([
            'submitted_by_user_id' => $user->id, 'submitter_display_name' => 'Gone Soon',
        ]);

        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'deletemepassword'])
            ->assertRedirect(route('scheduler.results.public'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('bof_sessions', [
            'id' => $session->id, 'submitted_by_user_id' => null, 'submitter_display_name' => 'Gone Soon',
        ]);
    }

    #[Test]
    #[TestDox('account deletion requires the correct password')]
    public function deletion_requires_correct_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('deletemepassword')]);

        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'wrongpassword'])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    #[Test]
    #[TestDox('the last active admin cannot delete their own account')]
    public function last_admin_cannot_delete_self(): void
    {
        $admin = $this->createAdmin(['password' => Hash::make('deletemepassword')]);

        $this->actingAs($admin)->delete(route('profile.destroy'), ['password' => 'deletemepassword'])
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
