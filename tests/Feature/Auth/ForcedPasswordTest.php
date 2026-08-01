<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use ConferenceTools\BoFScheduler\Enums\EventPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/** Feature tests for Forced password change. */
#[TestDox('Forced password change')]
class ForcedPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setPhase(EventPhase::Nomination);
    }

    #[Test]
    #[TestDox('a flagged user is redirected to the forced-change screen')]
    public function flagged_user_is_redirected(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->get('/bofs/sessions')->assertRedirect(route('password.forced.edit'));
        $this->actingAs($user)->get('/bofs/landing')->assertRedirect(route('password.forced.edit'));
    }

    #[Test]
    #[TestDox('the forced-change screen itself is reachable while flagged')]
    public function forced_change_screen_is_reachable(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->get(route('password.forced.edit'))->assertOk();
    }

    #[Test]
    #[TestDox('a flagged user may still log out')]
    public function flagged_user_can_log_out(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->post('/logout')->assertRedirect(route('scheduler.results.public'));
        $this->assertGuest();
    }

    #[Test]
    #[TestDox('completing the change clears the flag and forwards by phase')]
    public function completing_change_clears_flag(): void
    {
        $this->setPhase(EventPhase::Selection);
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->put(route('password.forced.update'), [
            'password' => 'brandnewpassword',
            'password_confirmation' => 'brandnewpassword',
        ])->assertRedirect(route('scheduler.selection.index'));

        $this->assertFalse($user->fresh()->must_change_password);
    }

    #[Test]
    #[TestDox('the forced change enforces the minimum password length')]
    public function forced_change_enforces_min_length(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->put(route('password.forced.update'), [
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->must_change_password);
    }
}
