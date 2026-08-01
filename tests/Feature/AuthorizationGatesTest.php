<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * The host defines "who may administer each package area" as a set of gates,
 * each currently meaning "any admin user". Both outcomes matter.
 */
#[TestDox('Host authorization gates')]
class AuthorizationGatesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return iterable<string, array{string}>
     */
    public static function hostGates(): iterable
    {
        yield 'scheduler' => ['manage-scheduler'];
        yield 'registration' => ['manage-registration'];
        yield 'branding' => ['manage-branding'];
    }

    #[Test]
    #[TestDox('an admin is granted the gate while a non-admin is denied')]
    #[DataProvider('hostGates')]
    public function gate_grants_admins_and_denies_others(string $gate): void
    {
        $this->assertTrue(Gate::forUser($this->createAdmin())->allows($gate));
        $this->assertFalse(Gate::forUser($this->createUser())->allows($gate));
    }
}
