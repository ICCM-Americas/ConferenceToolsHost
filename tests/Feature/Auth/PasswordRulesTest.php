<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use ConferenceTools\BoFScheduler\Enums\EventPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/** Feature tests for Password policy (length-based only). */
#[TestDox('Password policy (length-based only)')]
class PasswordRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setPhase(EventPhase::Nomination);
    }

    #[Test]
    #[TestDox('user-set passwords require at least 12 characters')]
    public function user_passwords_require_min_length(): void
    {
        $this->post('/register', [
            'name' => 'Short',
            'email' => 'short@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    #[Test]
    #[TestDox('no character-composition rules are imposed')]
    public function no_composition_rules(): void
    {
        $this->post('/register', [
            'name' => 'Simple',
            'email' => 'simple@example.com',
            'password' => 'abcdefghijkl',
            'password_confirmation' => 'abcdefghijkl',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'simple@example.com']);
    }

    #[Test]
    #[TestDox('admin-set temporary passwords also require at least 12 characters')]
    public function temp_password_requires_min_length(): void
    {
        $admin = $this->createAdmin();
        $target = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.set-temporary-password', $target), [
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }
}
