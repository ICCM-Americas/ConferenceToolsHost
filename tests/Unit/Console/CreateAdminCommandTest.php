<?php

namespace Tests\Unit\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/** Unit tests for app:create-admin command. */
#[TestDox('app:create-admin command')]
class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('creates an admin with the supplied password')]
    public function creates_admin_with_password(): void
    {
        $this->artisan('app:create-admin', [
            'email' => 'boss@example.com',
            'name' => 'Boss',
            '--password' => 'longenoughpassword',
        ])->assertSuccessful();

        $user = User::where('email', 'boss@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->isAdmin());
        $this->assertFalse($user->is_locked);
    }

    #[Test]
    #[TestDox('generates and prints a password when none is supplied')]
    public function generates_password_when_omitted(): void
    {
        $this->artisan('app:create-admin', ['email' => 'gen@example.com', 'name' => 'Gen'])
            ->expectsOutputToContain('Generated password:')
            ->assertSuccessful();

        $this->assertTrue(User::where('email', 'gen@example.com')->exists());
    }

    #[Test]
    #[TestDox('promotes an existing user to admin')]
    public function promotes_existing_user(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.com', 'is_admin' => false]);

        $this->artisan('app:create-admin', [
            'email' => 'existing@example.com',
            'name' => 'Existing',
            '--password' => 'longenoughpassword',
        ])->assertSuccessful();

        $this->assertTrue($user->fresh()->isAdmin());
    }

    #[Test]
    #[TestDox('generates a display name from the email when none is supplied')]
    public function generates_display_name_when_blank(): void
    {
        $this->artisan('app:create-admin', [
            'email' => 'jane.doe@example.com',
            '--password' => 'longenoughpassword',
        ])
            ->expectsQuestion('Display name (leave blank to generate one)', '')
            ->assertSuccessful();

        $name = User::where('email', 'jane.doe@example.com')->value('name');
        $this->assertNotEmpty($name);
        $this->assertMatchesRegularExpression('/^(janed\d{5}|J4N3D03\d{3})$/', $name);
    }

    #[Test]
    #[TestDox('fails validation for a short password')]
    public function fails_for_short_password(): void
    {
        $this->artisan('app:create-admin', [
            'email' => 'short@example.com',
            'name' => 'Short',
            '--password' => 'short',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'short@example.com']);
    }

    #[Test]
    #[TestDox('fails validation for an invalid email')]
    public function fails_for_invalid_email(): void
    {
        $this->artisan('app:create-admin', [
            'email' => 'not-an-email',
            'name' => 'Bad',
            '--password' => 'longenoughpassword',
        ])->assertFailed();
    }
}
