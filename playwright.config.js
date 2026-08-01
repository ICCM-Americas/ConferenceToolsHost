// Black-box UI / JavaScript test suite for the host application.
//
// This is intentionally separate from the PHPUnit suite (tests/Feature,
// tests/Unit): it drives a real browser against a running copy of the app and
// exercises the client-side JavaScript that PHPUnit never touches (nav menus,
// the branding live-preview, the selection willing-to-facilitate toggle, the
// facilitator picker, the dashboard clock and the WebAuthn passkey client).
//
// Screenshots are captured for BOTH passes and failures (use.screenshot =
// 'on'), plus per-scenario screenshots written to tests/ui/screenshots, so the
// rendered UI can be eyeballed after a run. Traces and video are kept on
// failure for debugging.
import { defineConfig, devices } from '@playwright/test';
import { BASE_URL, PORT, HOST, artisanEnv } from './tests/ui/support/env.js';

export default defineConfig({
    testDir: './tests/ui',
    // support/ holds helpers and fixtures, not tests.
    testMatch: '**/*.spec.js',

    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: 0,
    timeout: 30_000,
    expect: { timeout: 7_000 },

    outputDir: './tests/ui/.results',
    reporter: [
        ['list'],
        ['html', { outputFolder: './tests/ui/.report', open: 'never' }],
    ],

    use: {
        baseURL: BASE_URL,
        screenshot: 'on',
        trace: 'retain-on-failure',
        video: 'retain-on-failure',
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],

    webServer: {
        // Rebuild the isolated database, THEN serve it — so the URL only goes
        // healthy once the schema + fixtures exist.
        command: `node tests/ui/prepare-db.js && php artisan serve --host=${HOST} --port=${PORT}`,
        url: BASE_URL,
        env: artisanEnv(),
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
    },
});
