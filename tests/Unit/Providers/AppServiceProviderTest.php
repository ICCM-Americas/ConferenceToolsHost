<?php

namespace Tests\Unit\Providers;

use App\Providers\AppServiceProvider;
use ConferenceTools\BoFScheduler\BoFSchedulerServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/** Unit tests for AppServiceProvider::packageInstalled. */
#[TestDox('AppServiceProvider::packageInstalled')]
class AppServiceProviderTest extends TestCase
{
    #[Test]
    #[TestDox('reports an installed optional package by its autoloadable provider class')]
    public function detects_an_installed_package(): void
    {
        // The bof-scheduler package is present in this workspace, so its service
        // provider class is autoloadable.
        $this->assertTrue(AppServiceProvider::packageInstalled(
            BoFSchedulerServiceProvider::class
        ));
    }

    #[Test]
    #[TestDox('reports a missing package as not installed without raising a warning')]
    public function reports_a_missing_package_as_absent(): void
    {
        // A provider class that cannot be autoloaded (e.g. a package removed after
        // deployment) must return false rather than surfacing an include warning.
        $this->assertFalse(AppServiceProvider::packageInstalled('App\\NoSuch\\PackageServiceProvider'));
    }
}
