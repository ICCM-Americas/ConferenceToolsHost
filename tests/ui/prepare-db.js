// Provisions a clean, isolated database for the browser tests.
//
// Run as the first half of the Playwright `webServer` command (see
// playwright.config.js) so the app only becomes reachable AFTER the schema and
// fixtures exist — otherwise `php artisan serve` would answer 500s for a missing
// database and Playwright would never see a healthy server.
//
// We deliberately rebuild from scratch every run so the suite is deterministic
// and order-independent: a fresh sqlite file, all migrations, then the
// UiTestingSeeder fixtures.
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import { PROJECT_ROOT, DB_PATH, artisanEnv } from './support/env.js';

const env = artisanEnv();

// Start from an empty database file (sqlite needs the file to exist to migrate).
fs.rmSync(DB_PATH, { force: true });
fs.writeFileSync(DB_PATH, '');

execFileSync(
    'php',
    [
        'artisan',
        'migrate:fresh',
        '--force',
        '--seed',
        '--seeder=Database\\Seeders\\UiTestingSeeder',
    ],
    { cwd: PROJECT_ROOT, env, stdio: 'inherit' },
);
