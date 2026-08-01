// "Export Current Registrations" / "Export Archive" (registration admin
// dashboard, admin-conference-card): each downloads a .ZIP of flattened CSVs
// (answers, groups, payments, room assignments, Prayer Pals). The UI database
// seeds no registrants, so row content is covered by the package's PHPUnit
// suite (RegistrationAnswerFlattenerTest, RegistrationExportBundleTest); this
// spec proves the buttons are wired up and produce a real .ZIP download.
import fs from 'node:fs';
import { test, expect, loginAsAdmin } from './support/helpers.js';

test.describe('conference registration data exports', () => {
    test('an admin downloads the current registrations export', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/registration/admin');

        const downloadPromise = page.waitForEvent('download');
        await page.getByRole('link', { name: 'Export Current Registrations' }).click();
        const download = await downloadPromise;

        expect(download.suggestedFilename()).toMatch(/^registrations-\d{8}-\d{6}\.zip$/);
        const contents = fs.readFileSync(await download.path());
        // The .ZIP local-file-header signature — a real archive, not a stub.
        expect(contents.subarray(0, 4).toString('hex')).toBe('504b0304');
    });

    test('the archive export is hidden until a conference has been archived, then downloads', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/registration/admin');

        await expect(page.getByRole('link', { name: 'Export Archive' })).toHaveCount(0);

        page.once('dialog', (dialog) => dialog.accept());
        await page.getByRole('button', { name: 'Start Next Conference' }).click();
        await expect(page.locator('.alert-success')).toContainText('The next conference has been started.');

        const downloadPromise = page.waitForEvent('download');
        await page.getByRole('link', { name: 'Export Archive' }).click();
        const download = await downloadPromise;

        expect(download.suggestedFilename()).toMatch(/^archive-\d{8}-\d{6}\.zip$/);
    });
});
