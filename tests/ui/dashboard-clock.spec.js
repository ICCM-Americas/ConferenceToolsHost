// Exercises the inline scripts in packages/.../resources/views/admin/dashboard.blade.php:
// the browser-detected local time and time zone are injected client-side so an
// admin can spot a server/browser time-zone mismatch, and the Results PDF is
// generated client-side from the results.pdf JSON route.
import fs from 'node:fs';
import { test, expect, shot, loginAsAdmin } from './support/helpers.js';

test.describe('scheduler admin dashboard clock', () => {
    test('browser time and time zone are populated by JavaScript', async ({ page }, testInfo) => {
        await loginAsAdmin(page);
        await page.goto('/bofs/admin');

        const time = page.locator('#browser-time');
        const tz = page.locator('#browser-tz');

        // Both start as the "…" placeholder and are replaced on load.
        await expect(time).not.toHaveText('…');
        await expect(tz).not.toHaveText('…');

        // The time zone should look like a real IANA zone (e.g. "Europe/London"
        // or "UTC"), proving Intl ran rather than leaving the placeholder.
        await expect(tz).toHaveText(/^[A-Za-z]+(\/[A-Za-z0-9_+-]+)*$/);

        await shot(page, testInfo, 'dashboard-clock');
    });

    test('the results PDF is generated in the browser and downloads as a real PDF', async ({ page, consoleErrors }) => {
        await loginAsAdmin(page);
        await page.goto('/bofs/admin');

        // End to end through the client-side pipeline: the results.pdf route's
        // JSON, the pinned jsPDF/AutoTable from unpkg, and the branding
        // package's font route. Needs network access to unpkg — the same
        // dependency the real export has.
        const downloadPromise = page.waitForEvent('download');
        await page.getByRole('button', { name: 'Results PDF' }).click();
        const download = await downloadPromise;
        expect(download.suggestedFilename()).toMatch(/^results-\d{8}-\d{6}\.pdf$/);

        const contents = fs.readFileSync(await download.path());
        expect(contents.subarray(0, 5).toString()).toBe('%PDF-');
        // Real output, not a stub: the embedded DejaVu faces alone are far
        // bigger than this.
        expect(contents.length).toBeGreaterThan(10000);

        expect(consoleErrors).toEqual([]);
    });
});
