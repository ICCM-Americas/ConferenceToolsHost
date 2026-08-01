<?php

namespace Tests\Unit\Support;

use App\Models\User;
use App\Support\LastAdminGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/** Unit tests for LastAdminGuard. */
#[TestDox('LastAdminGuard')]
class LastAdminGuardTest extends TestCase
{
    use RefreshDatabase;

    /** The guard under test. */
    private function guard(): LastAdminGuard
    {
        return app(LastAdminGuard::class);
    }

    #[Test]
    #[TestDox('counts only admins who are not locked')]
    public function counts_active_admins(): void
    {
        User::factory()->admin()->create();
        User::factory()->admin()->create();
        User::factory()->admin()->locked()->create();
        User::factory()->create();

        $this->assertSame(2, $this->guard()->activeAdminCount());
    }

    #[Test]
    #[TestDox('blocks disabling the last active admin')]
    public function blocks_last_active_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($this->guard()->wouldRemoveLastActiveAdmin($admin));
    }

    #[Test]
    #[TestDox('allows disabling an admin while another active admin remains')]
    public function allows_when_another_active_admin_exists(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create();

        $this->assertFalse($this->guard()->wouldRemoveLastActiveAdmin($admin));
    }

    #[Test]
    #[TestDox('never blocks for a user who is not an active admin')]
    public function ignores_non_active_admins(): void
    {
        $plain = User::factory()->create();
        $lockedAdmin = User::factory()->admin()->locked()->create();

        $this->assertFalse($this->guard()->wouldRemoveLastActiveAdmin($plain));
        $this->assertFalse($this->guard()->wouldRemoveLastActiveAdmin($lockedAdmin));
    }
}
