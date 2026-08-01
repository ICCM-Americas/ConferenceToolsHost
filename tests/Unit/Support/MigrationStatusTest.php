<?php

namespace Tests\Unit\Support;

use App\Support\MigrationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/** Unit tests for MigrationStatus (admin dashboard "updates pending" check). */
#[TestDox('MigrationStatus (admin dashboard "updates pending" check)')]
class MigrationStatusTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('every migration file counts as pending when the ledger is empty')]
    public function counts_pending_against_the_ledger(): void
    {
        // Emptying the ledger (the table still exists) means none of the
        // migration files are recorded as run, so all of them are pending.
        DB::table('migrations')->delete();

        $this->assertGreaterThan(0, MigrationStatus::pendingCount());
        $this->assertTrue(MigrationStatus::hasPending());
    }

    #[Test]
    #[TestDox('every migration file counts as pending when the ledger table is missing')]
    public function counts_all_files_when_the_ledger_is_missing(): void
    {
        // A brand-new database has no migrations table at all; every file is
        // then pending (the repositoryExists() === false branch).
        Schema::dropIfExists('migrations');

        $this->assertGreaterThan(0, MigrationStatus::pendingCount());
        $this->assertTrue(MigrationStatus::hasPending());
    }

    #[Test]
    #[TestDox('a failure to read the migration state reports zero rather than throwing')]
    public function returns_zero_when_the_migrator_throws(): void
    {
        // The check must never take the dashboard down: if the migrator can't be
        // read, report "nothing pending" instead of surfacing the exception.
        $migrator = \Mockery::mock();
        $migrator->shouldReceive('paths')->andThrow(new \RuntimeException('boom'));
        $this->app->instance('migrator', $migrator);

        $this->assertSame(0, MigrationStatus::pendingCount());
        $this->assertFalse(MigrationStatus::hasPending());
    }
}
