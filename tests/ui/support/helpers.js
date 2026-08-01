// Shared helpers for the black-box UI suite.
import { test as base, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { ADMIN, USER } from './env.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const SCREENSHOT_DIR = path.resolve(__dirname, '..', 'screenshots');

/**
 * Log in through the real form (black-box: no session faking) and wait until we
 * land somewhere other than the login page.
 */
export async function login(page, credentials) {
    await page.goto('/login');
    await page.fill('#email', credentials.email);
    await page.fill('#password', credentials.password);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 15_000 });
}

export const loginAsAdmin = (page) => login(page, ADMIN);
export const loginAsUser = (page) => login(page, USER);

/**
 * Write a named, full-page screenshot into tests/ui/screenshots AND attach it to
 * the HTML report, so successes can be eyeballed alongside the auto-captured
 * failure screenshots.
 */
export async function shot(page, testInfo, name) {
    fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
    const file = path.join(SCREENSHOT_DIR, `${name}.png`);
    const buffer = await page.screenshot({ path: file, fullPage: true });
    await testInfo.attach(name, { body: buffer, contentType: 'image/png' });
    return file;
}

/**
 * `test` extended with a `consoleErrors` fixture that records page-level console
 * errors and uncaught exceptions for the duration of a test. Specs assert it is
 * empty to catch JavaScript that throws on load.
 */
export const test = base.extend({
    consoleErrors: async ({ page }, use) => {
        const errors = [];
        page.on('console', (msg) => {
            if (msg.type() === 'error') errors.push(msg.text());
        });
        page.on('pageerror', (err) => errors.push(String(err)));
        await use(errors);
    },
});

export { expect };
