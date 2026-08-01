// "Start Next Conference" (registration admin dashboard): rolls the
// conference year forward and archives + clears all current registration
// data (answers, groups, guests, payments, room and prayer pals assignments)
// so people can register again (registration::partials.admin-conference-card).
// The PHPUnit feature suite (RegistrationArchiverTest) covers that the data
// actually gets archived and wiped; this spec drives the button itself
// through a real browser (confirm dialog, request, flash message, updated year).
import { test, expect, shot, loginAsAdmin } from './support/helpers.js';

test.describe('start next conference', () => {
    test('an admin starts the next conference from the dashboard', async ({ page }, testInfo) => {
        await loginAsAdmin(page);
        await page.goto('/registration/admin');

        await page.fill('#conference_name', 'ICCM Americas');
        await page.fill('#conference_year', '2026');
        await page.getByRole('button', { name: 'Save Conference' }).click();
        await expect(page.locator('.alert-success')).toContainText('The conference has been saved.');

        const startNextButton = page.getByRole('button', { name: 'Start Next Conference' });
        await expect(startNextButton).toBeVisible();
        await shot(page, testInfo, 'conference-next-card');

        page.once('dialog', (dialog) => dialog.accept());
        await startNextButton.click();

        await expect(page.locator('.alert-success')).toContainText('The next conference has been started.');
        await expect(page.locator('#conference_year')).toHaveValue('2027');
        await expect(page.locator('.card-body', { hasText: 'Complete' }).first()).toContainText('0');
        await shot(page, testInfo, 'conference-next-done');
    });
});
