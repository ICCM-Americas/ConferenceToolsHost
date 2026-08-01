// Shared configuration for the black-box UI suite, imported by both the
// Playwright config and the global setup so the app-under-test and the tests
// always agree on the port and the (isolated) database.
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// Project root (three levels up from tests/ui/support).
export const PROJECT_ROOT = path.resolve(__dirname, '..', '..', '..');

// Use "localhost" (not 127.0.0.1): WebAuthn rejects bare IP literals as relying-
// party ids, but treats localhost as a secure, valid origin for the passkey test.
export const HOST = 'localhost';
export const PORT = Number(process.env.UI_TEST_PORT ?? 8123);
export const BASE_URL = `http://${HOST}:${PORT}`;

// A dedicated sqlite file so the suite never touches the developer's dev/.env
// database. Recreated from scratch on every run by the global setup.
export const DB_PATH = path.join(PROJECT_ROOT, 'database', 'ui-testing.sqlite');

// Demo accounts seeded by Database\Seeders\UiTestingSeeder.
export const ADMIN = { email: 'admin@example.com', password: 'password-please-change' };
export const USER = { email: 'user@example.com', password: 'password-please-change' };

// Environment overrides handed to `php artisan` (serve + migrate). Laravel loads
// .env immutably, so real process env vars win — this redirects the app onto the
// isolated sqlite database and the test port without editing any .env file.
export function artisanEnv() {
    return {
        ...process.env,
        APP_ENV: 'local',
        APP_URL: BASE_URL,
        APP_DEBUG: 'true',
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: DB_PATH,
        SESSION_DRIVER: 'file',
        MAIL_MAILER: 'log',
        // Keep WebAuthn relying-party id stable for the virtual authenticator.
        CACHE_STORE: 'file',
        // The suite logs in as the same handful of seeded accounts (ADMIN/USER)
        // across ~30 spec files on one long-lived server process sharing one
        // cache store — that would otherwise trip the real 5/minute login
        // throttle meant for credential guessing. See config/security.php.
        LOGIN_RATE_LIMIT: '1000',
    };
}
