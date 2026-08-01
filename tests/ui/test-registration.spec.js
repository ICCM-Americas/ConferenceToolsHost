// Admin test drive of the registration process: the "Test Registration" nav
// button (secondary, unlike the primary console-page buttons) walks the real
// wizard while registration is still closed (the UI database seeds no window),
// records nothing — the dashboard counters stay at zero — and sends both
// completion emails to the signed-in admin's own address.
//
// The wizard's first step batches the ordinary questions (badge name, pass,
// options-editor); the guest/group trigger questions are each their own
// reactive step (see RegistrationWizard::expandSection()) and, with nothing
// else configured after them, the group step is the wizard's last — its
// button already reads "Register".
import { test, expect, shot, loginAsAdmin } from './support/helpers.js';

test.describe('test registration', () => {
    test('an admin test drives the wizard while registration is closed', async ({ page }, testInfo) => {
        await loginAsAdmin(page);

        // The nav offers the test drive as a secondary button.
        await page.goto('/registration/admin');
        const testButton = page.getByRole('link', { name: 'Test Registration' });
        await expect(testButton).toHaveClass(/btn-secondary/);
        await shot(page, testInfo, 'test-registration-nav');

        // Registration is closed, yet the test drive opens straight onto the
        // wizard's first step, under the test-mode banner.
        await testButton.click();
        await expect(page.locator('.alert-secondary')).toContainText('Test mode: nothing you enter will be recorded');

        // The wizard's first step renders with the banner; its own submit
        // reads "Next" since two more (reactive) steps follow it.
        await expect(page.locator('.alert-secondary')).toContainText('Test mode: nothing you enter will be recorded');
        await expect(page.locator('body')).toContainText('UI Test Details');

        // The banner's exit button leads to the Questions console, deep-linked
        // by a plain HTML anchor to the section being tested.
        await page.getByRole('link', { name: 'Exit Test Mode' }).click();
        await expect(page).toHaveURL(/\/registration\/admin\/questions#section-\d+$/);
        await expect(page.locator('.section', { hasText: 'UI Test Details' })).toBeInViewport();

        // Returning to the test drive resumes the run on the same step.
        await page.goto('/registration/admin/test-registration');
        await expect(page.locator('body')).toContainText('UI Test Details');

        await page.fill('input[name="ui_test_badge_name"]', 'Ada Lovelace');
        await shot(page, testInfo, 'test-registration-step');
        await page.getByRole('button', { name: 'Next' }).click();

        // The guest trigger, its own step — answer "No".
        await expect(page.locator('body')).toContainText('Are you registering one or more non-attending guests?');
        await page.getByRole('radio', { name: 'No', exact: true }).click();
        await page.getByRole('button', { name: 'Next' }).click();

        // The group trigger, the wizard's last step here: the cost summary
        // recaps the seeded flat conference fee and the running total, and
        // the button already reads "Register".
        await expect(page.locator('body')).toContainText('Are you registering a group for your organization?');
        const costSummary = page.locator('[data-cost-summary]');
        await expect(costSummary).toContainText('Cost Summary');
        await expect(costSummary).toContainText('UI Test Conference Fee');
        await expect(costSummary).toContainText('$ 100.00');
        await expect(costSummary).toContainText('Total');

        await page.getByRole('radio', { name: 'No', exact: true }).click();
        await page.getByRole('button', { name: 'Register' }).click();

        // Back on the dashboard: the run is confirmed, both emails went to the
        // admin's own address, and nothing was recorded — the registration
        // counters still read zero.
        await expect(page).toHaveURL(/\/registration\/admin$/);
        await expect(page.locator('.alert-success')).toContainText(
            'The registrant confirmation and the administrator notification were both sent to admin@example.com. Nothing was recorded.',
        );
        await expect(page.locator('body')).toContainText('Registrations');
        await expect(page.locator('.card-body', { hasText: 'Complete' }).first()).toContainText('0');
        await shot(page, testInfo, 'test-registration-completed');
    });

    test('a validation error keeps the admin on the test step', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/registration/admin/test-registration');

        // Submitting the required badge-name question empty is rejected
        // server-side, exactly as in the real flow. Empty the field and strip
        // its "required" so the browser lets the post through to the server.
        const field = page.locator('input[name="ui_test_badge_name"]');
        await field.evaluate((input) => {
            input.value = '';
            input.removeAttribute('required');
        });
        await page.getByRole('button', { name: 'Next' }).click();

        await expect(page).toHaveURL(/\/registration\/admin\/test-registration$/);
        await expect(page.locator('.invalid-feedback, .alert-danger').first()).toBeVisible();
    });
});
