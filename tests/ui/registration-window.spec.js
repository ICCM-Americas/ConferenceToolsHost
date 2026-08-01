// Registration window: an admin opens and closes registration from the
// registration dashboard, which gates the registrant-facing pages and the
// home booking card. The UI database seeds no window, so registration starts
// closed. The email addresses the open action requires are runtime settings
// configured through the admin "Emails" console (not .env), and the closed
// page's message follows the window state (see the "Closed Page" console).
import { test, expect, shot, loginAsAdmin } from './support/helpers.js';

const CLOSED_MESSAGE = 'Registration is currently closed.';

test.describe('registration window', () => {
    test('an admin configures addresses, then opens and closes registration', async ({ page }, testInfo) => {
        await loginAsAdmin(page);

        // Seeded closed with no dates: the landing page answers with the
        // generic closed message and the home page offers no booking card
        // (only the admin console link).
        await page.goto('/registration');
        await expect(page.locator('body')).toContainText(CLOSED_MESSAGE);
        await page.goto('/');
        await expect(page.locator('a[href$="/registration/register"]')).toHaveCount(0);

        // Without the email addresses the open action is refused, pointing
        // the admin at the Emails console.
        await page.goto('/registration/admin');
        await expect(page.locator('.badge')).toContainText('Registration is closed');
        await page.getByRole('button', { name: 'Open Registration Now' }).click();
        await expect(page.locator('.alert-danger')).toContainText('configure the administrator and from addresses');

        // Configure the administrator address on the Emails console.
        await page.goto('/registration/admin/emails');
        await page.fill('#admin_email', 'registration-admin@example.com');
        await page.getByRole('button', { name: 'Save' }).first().click();
        await expect(page.locator('.alert-success')).toContainText('The addresses have been saved.');

        // Record the conference edition, so the closed page can name it once
        // registration has closed.
        await page.goto('/registration/admin');
        await page.fill('#conference_name', 'ICCM Test');
        await page.fill('#conference_year', '2035');
        await page.getByRole('button', { name: 'Save Conference' }).click();
        await expect(page.locator('.alert-success')).toContainText('The conference has been saved.');

        // Open registration from the dashboard's window card.
        await page.getByRole('button', { name: 'Open Registration Now' }).click();
        await expect(page.locator('.alert-success')).toContainText('Registration is now open.');
        await expect(page.locator('.badge')).toContainText('Registration is open');
        await shot(page, testInfo, 'registration-window-open');

        // The landing page, the wizard entry and the booking card are back.
        await page.goto('/registration');
        await expect(page.locator('body')).not.toContainText(CLOSED_MESSAGE);
        await page.goto('/registration/register');
        await expect(page.locator('body')).not.toContainText(CLOSED_MESSAGE);
        await page.goto('/');
        await expect(page.locator('a[href$="/registration/register"]')).toHaveCount(1);

        // Close it again: the gate falls back into place, and the closed page
        // now names the conference whose registration just ended.
        await page.goto('/registration/admin');
        await page.getByRole('button', { name: 'Close Registration Now' }).click();
        await expect(page.locator('.alert-success')).toContainText('Registration is now closed.');
        await expect(page.locator('.badge')).toContainText('Registration is closed');
        await page.goto('/registration');
        await expect(page.locator('body')).toContainText('Registration for ICCM Test 2035 has ended');
        await shot(page, testInfo, 'registration-window-closed');

        // Scheduling a future open date switches the closed page over to the
        // "registration will open…" announcement, with the date, time and
        // time zone resolved from the schedule.
        await page.goto('/registration/admin');
        await page.fill('#opens_at', '2035-03-05T09:30');
        await page.fill('#closes_at', '');
        await page.getByRole('button', { name: 'Save Window' }).click();
        await expect(page.locator('.alert-success')).toContainText('The registration window has been saved.');
        await page.goto('/registration');
        await expect(page.locator('body')).toContainText('Registration will open March 5, 2035 at 9:30 AM (UTC).');
        await shot(page, testInfo, 'registration-window-scheduled');
    });
});
