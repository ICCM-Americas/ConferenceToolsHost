<?php

namespace Tests\Unit\Providers;

use App\Providers\AppServiceProvider;
use ConferenceTools\BoFScheduler\BoFSchedulerServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/** Unit tests for AppServiceProvider::packageInstalled. */
#[TestDox('AppServiceProvider::packageInstalled')]
class AppServiceProviderTest extends TestCase
{
    public static function classNameProvider()
    {
        return [
            'existing class' => [BoFSchedulerServiceProvider::class, true],
            'non-existing class' => ['App\\NoSuch\\PackageServiceProvider', false],
        ];
    }

    #[Test]
    #[TestDox('reports an installed optional package by its autoloadable provider class')]
    #[DataProvider('classNameProvider')]
    public function package_installed_works_for(string $className, bool $exists): void
    {
        $this->assertEquals($exists, AppServiceProvider::packageInstalled($className));
    }
}
