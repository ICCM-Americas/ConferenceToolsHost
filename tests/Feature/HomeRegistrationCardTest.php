<?php

namespace Tests\Feature;

use App\Models\User;
use ConferenceTools\Registration\Models\Group;
use ConferenceTools\Registration\Models\GroupInvite;
use ConferenceTools\Registration\Models\Payment;
use ConferenceTools\Registration\Services\RegistrationEmails;
use ConferenceTools\Registration\Services\RegistrationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * The home dashboard's registration card: its several states (registered or
 * not, open or not, paid or not, group leader or not) — see home.blade.php
 * and AppServiceProvider's "home" view composer.
 */
#[TestDox('Home dashboard registration card')]
class HomeRegistrationCardTest extends TestCase
{
    use RefreshDatabase;

    /** Attach a user to a new group, bypassing the host User model's $fillable (these columns are package-owned). */
    private function register(User $user, bool $isGroupAdmin = true): Group
    {
        $group = Group::create(['name' => 'Test Group', 'is_group' => false]);
        $user->forceFill(['group_id' => $group->id, 'is_group_admin' => $isGroupAdmin])->save();

        return $group;
    }

    #[Test]
    #[TestDox('a not-yet-registered user sees the "not open" message instead of a countdown while no window is scheduled')]
    public function not_registered_and_closed_shows_the_home_message(): void
    {
        $this->actingAs($this->createUser())->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('registration.register'))
            ->assertSee('has closed');
    }

    #[Test]
    #[TestDox('a not-yet-registered user sees the countdown message while a future open date is scheduled')]
    public function not_registered_and_before_open_shows_the_countdown_message(): void
    {
        app(RegistrationEmails::class)->update('admin@example.com', null);
        app(RegistrationStatus::class)->schedule(now()->addDays(3), null);

        $this->actingAs($this->createUser())->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('registration.register'))
            ->assertSee('Registration opens');
    }

    #[Test]
    #[TestDox('a registered, unpaid user is offered Modify while registration is open')]
    public function registered_open_unpaid_offers_modify(): void
    {
        $this->openRegistration();
        $user = $this->createUser();
        $this->register($user);

        $this->actingAs($user)->get(route('home'))
            ->assertOk()
            ->assertSee(route('registration.mine'))
            ->assertSee(route('registration.mine.edit'));
    }

    #[Test]
    #[TestDox('a registered, paid user is offered the administrator contact instead of Modify')]
    public function registered_paid_offers_administrator_contact(): void
    {
        $this->openRegistration();
        $user = $this->createUser();
        $this->register($user);
        Payment::factory()->paid()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('home'))
            ->assertOk()
            ->assertSee(route('registration.mine'))
            ->assertDontSee(route('registration.mine.edit'))
            ->assertSee('mailto:admin@example.com', false);
    }

    #[Test]
    #[TestDox('a registered user is offered the administrator contact once registration closes, even if unpaid')]
    public function registered_closed_offers_administrator_contact(): void
    {
        $this->openRegistration();
        $user = $this->createUser();
        $this->register($user);
        app(RegistrationStatus::class)->close();

        $this->actingAs($user)->get(route('home'))
            ->assertOk()
            ->assertSee(route('registration.mine'))
            ->assertDontSee(route('registration.mine.edit'))
            ->assertSee('mailto:admin@example.com', false);
    }

    #[Test]
    #[TestDox('a group leader sees their group members and pending invites')]
    public function group_leader_sees_members_and_pending_invites(): void
    {
        $this->openRegistration();
        $leader = $this->createUser();
        $group = $this->register($leader);
        $member = $this->createUser();
        $member->forceFill(['group_id' => $group->id, 'is_group_admin' => false])->save();

        GroupInvite::create(['group_id' => $group->id, 'token' => bin2hex(random_bytes(8))]);

        $this->actingAs($leader)->get(route('home'))
            ->assertOk()
            ->assertSee(__('home.group_members_heading'))
            ->assertSee(__('home.group_member_registered'))
            ->assertSee(__('home.group_member_not_registered'));
    }

    #[Test]
    #[TestDox('a plain group member does not see the group members section')]
    public function plain_member_does_not_see_group_section(): void
    {
        $this->openRegistration();
        $leader = $this->createUser();
        $group = $this->register($leader);
        $member = $this->createUser();
        $member->forceFill(['group_id' => $group->id, 'is_group_admin' => false])->save();

        $this->actingAs($member)->get(route('home'))
            ->assertOk()
            ->assertDontSee(__('home.group_members_heading'));
    }
}
