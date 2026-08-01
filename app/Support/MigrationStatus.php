<?php

namespace App\Support;

/**
 * Reports whether the application has database migrations that have not been
 * run yet, so the admin dashboard can prompt an administrator to apply them.
 *
 * The check goes through the framework's migrator (the same source of truth as
 * `php artisan migrate:status`): it lists every migration file — the host's own
 * plus each installed package's — and diffs them against the ledger of migrations
 * already run. When the ledger table itself is missing (a brand-new database),
 * every migration counts as pending.
 */
class MigrationStatus
{
    /** Number of migrations that have not been run yet. */
    public static function pendingCount(): int
    {
        try {
            $migrator = app('migrator');

            // The migrator only knows the paths packages registered via
            // loadMigrationsFrom(); the host's own database/migrations is applied
            // implicitly by the migrate command, so add it here explicitly.
            $paths = array_merge($migrator->paths(), [database_path('migrations')]);
            $files = $migrator->getMigrationFiles($paths);

            if (! $migrator->repositoryExists()) {
                return count($files);
            }

            $ran = $migrator->getRepository()->getRan();

            return count(array_diff(array_keys($files), $ran));
        } catch (\Throwable) {
            // A diagnostic must never take the dashboard down; if the migration
            // state can't be read, simply don't prompt.
            return 0;
        }
    }

    /** Whether any migration has not been run yet. */
    public static function hasPending(): bool
    {
        return self::pendingCount() > 0;
    }
}
