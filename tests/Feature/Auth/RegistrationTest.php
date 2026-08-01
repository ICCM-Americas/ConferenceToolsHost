<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use ConferenceTools\BoFScheduler\Enums\EventPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/** Feature tests for Registration. */
#[TestDox('Registration')]
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setPhase(EventPhase::Nomination);
    }

    #[Test]
    #[TestDox('the registration screen renders')]
    public function registration_screen_renders(): void
    {
        $this->get(route('register'))->assertOk();
    }

    #[Test]
    #[TestDox('a new user can register and is logged in')]
    public function users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'longenoughpassword',
            'password_confirmation' => 'longenoughpassword',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
        $response->assertRedirect(route('scheduler.sessions.index'));
    }

    #[Test]
    #[TestDox('a blank display name is generated from the email (standard or leet)')]
    public function display_name_is_generated_when_blank(): void
    {
        $this->post('/register', [
            'name' => '',
            'email' => 'jane.doe@example.com',
            'password' => 'longenoughpassword',
            'password_confirmation' => 'longenoughpassword',
        ]);

        $this->assertAuthenticated();
        $name = User::where('email', 'jane.doe@example.com')->value('name');

        // Standard: "janed" + 5 digits. Leet: "J4N3D03" + 3 digits.
        $this->assertMatchesRegularExpression('/^(janed\d{5}|J4N3D03\d{3})$/', $name);
    }

    #[Test]
    #[TestDox('a supplied display name is kept as-is')]
    public function supplied_display_name_is_kept(): void
    {
        $this->post('/register', [
            'name' => 'Jane Doe',
            'email' => 'jane2@example.com',
            'password' => 'longenoughpassword',
            'password_confirmation' => 'longenoughpassword',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'jane2@example.com', 'name' => 'Jane Doe']);
    }

    #[Test]
    #[TestDox('registration requires a unique email')]
    public function registration_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/register', [
            'name' => 'Dup',
            'email' => 'taken@example.com',
            'password' => 'longenoughpassword',
            'password_confirmation' => 'longenoughpassword',
        ])->assertSessionHasErrors('email');
    }

    #[Test]
    #[TestDox('registration requires a confirmed password')]
    public function registration_requires_confirmation(): void
    {
        $this->post('/register', [
            'name' => 'Mismatch',
            'email' => 'mismatch@example.com',
            'password' => 'longenoughpassword',
            'password_confirmation' => 'differentpassword',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }
}
