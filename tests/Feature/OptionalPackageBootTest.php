<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use ConferenceTools\BoFScheduler\BoFSchedulerServiceProvider;
use ConferenceTools\Registration\RegistrationServiceProvider;
use Illuminate\Foundation\PackageManifest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * The conference-tools packages must be safe to deploy without — including
 * having their directory removed after a build. Auto-discovery would bake a
 * provider reference into bootstrap/cache/packages.php that fatals at boot when
 * the files are gone, so discovery is disabled and the host registers each
 * provider itself, guarded by a safe presence check.
 */
#[TestDox('Optional conference-tools packages (host-managed, safe when absent)')]
class OptionalPackageBootTest extends TestCase
{
    #[Test]
    #[TestDox('the optional providers are excluded from package auto-discovery')]
    public function optional_providers_are_not_auto_discovered(): void
    {
        // If these reappear here, composer.json "dont-discover" has regressed and
        // a deployment without the package will fatal on the cached provider.
        $discovered = $this->app->make(PackageManifest::class)->providers();

        $this->assertNotContains(BoFSchedulerServiceProvider::class, $discovered);
        $this->assertNotContains(RegistrationServiceProvider::class, $discovered);
    }

    #[Test]
    #[TestDox('the host registers each installed package provider itself')]
    public function host_registers_installed_package_providers(): void
    {
        // Both packages are installed in the test suite, so AppServiceProvider
        // must have registered them in place of auto-discovery.
        $loaded = $this->app->getLoadedProviders();

        $this->assertArrayHasKey(BoFSchedulerServiceProvider::class, $loaded);
        $this->assertArrayHasKey(RegistrationServiceProvider::class, $loaded);
    }

    #[Test]
    #[TestDox('packageInstalled() reports a present package as installed')]
    public function package_installed_is_true_for_present_package(): void
    {
        $this->assertTrue(AppServiceProvider::packageInstalled(RegistrationServiceProvider::class));
    }

    #[Test]
    #[TestDox('packageInstalled() returns false for an absent package without throwing')]
    public function package_installed_is_false_and_safe_for_absent_package(): void
    {
        // Mirrors a removed package whose class can no longer be autoloaded. The
        // check must report false rather than surfacing an include/autoload error
        // (with an optimized classmap a bare class_exists() would throw).
        $this->assertFalse(AppServiceProvider::packageInstalled('ConferenceTools\\Absent\\AbsentServiceProvider'));
    }
}
