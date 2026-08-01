<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/** Feature tests for Password reset. */
#[TestDox('Password reset')]
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('the forgot-password screen renders')]
    public function forgot_password_screen_renders(): void
    {
        $this->get(route('password.request'))->assertOk();
    }

    #[Test]
    #[TestDox('a reset link is sent for a known email')]
    public function reset_link_sent_for_known_email(): void
    {
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    #[Test]
    #[TestDox('requesting a reset for an unknown email reports an error')]
    public function reset_link_error_for_unknown_email(): void
    {
        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHasErrors('email');
    }

    #[Test]
    #[TestDox('a mail failure during reset is reported and shown as a friendly error, not a 500')]
    public function reset_link_mail_failure_is_reported_as_friendly_error(): void
    {
        $user = User::factory()->create();
        Password::shouldReceive('sendResetLink')->once()->andThrow(new \RuntimeException('smtp down'));
        $handler = $this->spy(ExceptionHandler::class);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasErrors('email');

        $handler->shouldHaveReceived('report')->once();
    }

    #[Test]
    #[TestDox('the reset-password screen renders for a token')]
    public function reset_password_screen_renders(): void
    {
        $this->get(route('password.reset', ['token' => 'sometoken']))->assertOk();
    }

    #[Test]
    #[TestDox('a valid token resets the password and clears the forced-change flag')]
    public function valid_token_resets_password(): void
    {
        Event::fake();
        $user = User::factory()->create(['must_change_password' => true]);
        $token = Password::createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'brandnewpassword',
            'password_confirmation' => 'brandnewpassword',
        ])->assertRedirect(route('login'))->assertSessionHas('status');

        $user = $user->fresh();
        $this->assertTrue(Hash::check('brandnewpassword', $user->password));
        $this->assertFalse($user->must_change_password);
        Event::assertDispatched(PasswordReset::class);
    }

    #[Test]
    #[TestDox('an invalid token is rejected')]
    public function invalid_token_rejected(): void
    {
        $user = User::factory()->create();

        $this->post(route('password.store'), [
            'token' => 'not-a-valid-token',
            'email' => $user->email,
            'password' => 'brandnewpassword',
            'password_confirmation' => 'brandnewpassword',
        ])->assertSessionHasErrors('email');
    }
}
