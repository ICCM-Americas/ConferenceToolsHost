<?php

namespace Tests\Feature\Admin;

use App\Support\MigrationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/** Feature tests for Run database migrations from the admin dashboard. */
#[TestDox('Run database migrations from the admin dashboard')]
class MigrateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('nothing is pending once every migration has run')]
    public function nothing_pending_when_migrated(): void
    {
        // RefreshDatabase applies every migration, so the status must be clear.
        $this->assertSame(0, MigrationStatus::pendingCount());
        $this->assertFalse(MigrationStatus::hasPending());
    }

    #[Test]
    #[TestDox('an admin can trigger migrations and is returned to the dashboard')]
    public function admin_can_run_migrations(): void
    {
        $this->actingAs($this->createAdmin())
            ->post(route('admin.migrate'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('status');
    }

    #[Test]
    #[TestDox('a non-admin cannot trigger migrations')]
    public function non_admin_cannot_run_migrations(): void
    {
        $this->actingAs($this->createUser())
            ->post(route('admin.migrate'))
            ->assertForbidden();
    }

    #[Test]
    #[TestDox('a guest cannot trigger migrations')]
    public function guest_cannot_run_migrations(): void
    {
        $this->post(route('admin.migrate'))->assertRedirect(route('login'));
    }
}
