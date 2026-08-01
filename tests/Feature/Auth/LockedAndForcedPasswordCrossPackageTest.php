<?php

namespace Tests\Feature\Auth;

use ConferenceTools\BoFScheduler\Enums\EventPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * A locked or must-change-password admin session must be diverted before it
 * reaches ANY package's admin routes, not just the host's own and
 * bof-scheduler's — config/registration.php and config/branding.php layer
 * "not.locked"/"password.current" on top of the packages' own portable
 * defaults exactly like config/bofscheduler.php already does.
 */
#[TestDox('Locked/forced-password accounts are diverted from every package\'s admin routes')]
class LockedAndForcedPasswordCrossPackageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setPhase(EventPhase::Nomination);
    }

    #[Test]
    #[DataProvider('adminRoutes')]
    #[TestDox('a locked admin session is redirected to login, not served the admin screen')]
    public function locked_admin_is_redirected(string $routeName): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);
        $admin->forceFill(['is_locked' => true])->save();

        $this->get(route($routeName))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    #[Test]
    #[DataProvider('adminRoutes')]
    #[TestDox('a must-change-password admin session is redirected to the forced-change screen')]
    public function must_change_password_admin_is_redirected(string $routeName): void
    {
        $admin = $this->createAdmin(['must_change_password' => true]);

        $this->actingAs($admin)->get(route($routeName))
            ->assertRedirect(route('password.forced.edit'));
    }

    /** @return array<string, array{0: string}> */
    public static function adminRoutes(): array
    {
        return [
            'registration admin dashboard' => ['registration.admin.dashboard'],
            'branding admin edit' => ['admin.branding.edit'],
            'scheduler admin dashboard' => ['scheduler.admin.dashboard'],
            'host admin dashboard' => ['admin.dashboard'],
        ];
    }
}
