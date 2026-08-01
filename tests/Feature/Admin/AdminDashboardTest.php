<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/** Feature tests for Host admin dashboard (branding + user management). */
#[TestDox('Host admin dashboard (branding + user management)')]
class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('an admin can view the host admin dashboard with site settings, branding and user links')]
    public function admin_can_view_dashboard(): void
    {
        $this->actingAs($this->createAdmin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.settings.edit'))
            ->assertSee(route('admin.branding.edit'))
            ->assertSee(route('admin.users.index'));
    }

    #[Test]
    #[TestDox('the dashboard hides the run-migrations prompt when the database is up to date')]
    public function dashboard_hides_migration_prompt_when_up_to_date(): void
    {
        // RefreshDatabase runs every migration, so nothing is pending and the
        // red "database update required" prompt must not appear.
        $this->actingAs($this->createAdmin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(__('admin.migrations_pending_title'))
            ->assertDontSee(route('admin.migrate'));
    }

    #[Test]
    #[TestDox('the dashboard summarizes registration and BoF data with links through to each package dashboard')]
    public function dashboard_shows_package_summaries(): void
    {
        $this->actingAs($this->createAdmin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('registration::admin.registrations'))
            ->assertSee(route('registration.admin.dashboard'))
            ->assertSee(__('bofscheduler::admin.nominated_topics'))
            ->assertSee(route(config('bofscheduler.route_name_prefix', 'bof.').'admin.dashboard'));
    }

    #[Test]
    #[TestDox('a non-admin cannot view the host admin dashboard')]
    public function non_admin_cannot_view_dashboard(): void
    {
        $this->actingAs($this->createUser())->get(route('admin.dashboard'))->assertForbidden();
    }

    #[Test]
    #[TestDox('a guest is redirected to login')]
    public function guest_redirected(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }
}
