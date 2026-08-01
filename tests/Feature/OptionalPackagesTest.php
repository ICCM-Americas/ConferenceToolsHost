<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/** Feature tests for Optional conference-tools packages (views hide controls when a package is absent). */
#[TestDox('Optional conference-tools packages (views hide controls when a package is absent)')]
class OptionalPackagesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('the home registration control offers to register when the package is installed and registration is open')]
    public function home_shows_booking_when_registration_installed(): void
    {
        // Both packages are installed in the test suite, so the flag is true.
        $this->openRegistration();

        $this->actingAs($this->createUser())->get(route('home'))
            ->assertOk()
            ->assertSee(route('registration.register'));
    }

    #[Test]
    #[TestDox('the home registration control does not offer to register while registration is closed')]
    public function home_hides_booking_while_registration_closed(): void
    {
        // No registration window has been opened (the default state); the
        // card still renders (with the "not open" message), just without a
        // link into the registration wizard.
        $this->actingAs($this->createUser())->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('registration.register'));
    }

    #[Test]
    #[TestDox('the home registration card is hidden entirely when the registration package is absent')]
    public function home_hides_booking_when_registration_absent(): void
    {
        View::share('registrationInstalled', false);

        $this->actingAs($this->createUser())->get(route('home'))
            ->assertOk()
            ->assertDontSee(__('nav.your_registration'))
            ->assertDontSee(route('registration.register'));
    }

    #[Test]
    #[TestDox('admin nav hides the BoFs and Registration menus for absent packages')]
    public function admin_nav_hides_absent_package_menus(): void
    {
        View::share('bof_schedulerInstalled', false);
        View::share('registrationInstalled', false);

        $this->actingAs($this->createAdmin())->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('scheduler.sessions.index'))
            ->assertDontSee(route('registration.admin.dashboard'));
    }

    #[Test]
    #[TestDox('admin nav shows both package menus when both packages are installed')]
    public function admin_nav_shows_installed_package_menus(): void
    {
        $this->actingAs($this->createAdmin())->get(route('home'))
            ->assertOk()
            ->assertSee(route('scheduler.sessions.index'))
            ->assertSee(route('registration.admin.dashboard'));
    }

    #[Test]
    #[TestDox('the admin dashboard renders without the package summary cards when both packages are absent')]
    public function admin_dashboard_renders_without_absent_package_cards(): void
    {
        // The dashboard @includes package-provided partials; guarding the includes
        // keeps the page rendering when a package (and its view namespace) is gone.
        View::share('bof_schedulerInstalled', false);
        View::share('registrationInstalled', false);

        $this->actingAs($this->createAdmin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(route('registration.admin.dashboard'))
            ->assertSee(route('admin.users.index'));
    }
}
