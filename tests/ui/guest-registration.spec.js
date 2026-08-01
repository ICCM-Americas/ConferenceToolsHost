// The non-attending guest flow: answering the trigger question detours to a
// Guest List hub where a registrant can add, edit and remove guests before
// continuing the wizard. Driven against the admin test drive (like
// test-registration.spec.js) so nothing is actually recorded.
//
// The wizard's first step batches the ordinary questions (badge name, pass,
// options-editor); the guest/group trigger questions are each their own
// reactive step (see RegistrationWizard::expandSection()), guest before
// group (see UiTestingSeeder's question positions).
import { test, expect, shot, loginAsAdmin } from './support/helpers.js';

test.describe('non-attending guests', () => {
    test('answering the trigger detours to the hub to add, edit and remove a guest', async ({ page }, testInfo) => {
        await loginAsAdmin(page);
        await page.goto('/registration/admin/test-registration');

        // Step 1: the ordinary batched questions.
        await page.fill('input[name="ui_test_badge_name"]', 'Ada Lovelace');
        await page.getByRole('button', { name: 'Next' }).click();

        // Step 2: the guest trigger, alone. Answering "Yes" detours to the
        // Guest List hub instead of advancing to the next (group) step.
        await expect(page.locator('body')).toContainText('Are you registering one or more non-attending guests?');
        await page.getByRole('radio', { name: 'Yes', exact: true }).click();
        await page.getByRole('button', { name: 'Next' }).click();

        await expect(page).toHaveURL(/\/registration\/admin\/test-registration\/guests$/);
        await expect(page.locator('body')).toContainText('Non-Attending Guests');
        await expect(page.locator('body')).toContainText('No guests added yet.');
        await shot(page, testInfo, 'guest-hub-empty');

        // Add a guest.
        await page.getByRole('link', { name: 'Add a Guest' }).click();
        await expect(page.locator('body')).toContainText('Guest type');
        await page.getByLabel('Adult', { exact: true }).check();
        await page.fill("input[name=\"ui_test_guest_name\"]", 'Stay Home');
        await shot(page, testInfo, 'guest-form');
        await page.getByRole('button', { name: 'Save Guest' }).click();

        // Back on the hub: the guest is listed with its type.
        await expect(page).toHaveURL(/\/registration\/admin\/test-registration\/guests$/);
        await expect(page.locator('body')).toContainText('Stay Home');
        await expect(page.locator('body')).toContainText('Adult');
        await shot(page, testInfo, 'guest-hub-with-guest');

        // Edit the guest.
        await page.getByRole('link', { name: 'Edit' }).click();
        await expect(page.locator('input[name="ui_test_guest_name"]')).toHaveValue('Stay Home');
        // The type is shown but is not itself editable on the edit form: no
        // radio choice, just a hidden mirror (so a guest_type-conditioned
        // question's live visibility toggle still has something to read from).
        await expect(page.locator('input[type="radio"][name="guest_type"]')).toHaveCount(0);
        await expect(page.locator('input[type="hidden"][name="guest_type"]')).toHaveValue('adult');
        await page.fill('input[name="ui_test_guest_name"]', 'Staying Home Still');
        await page.getByRole('button', { name: 'Save Guest' }).click();
        await expect(page.locator('body')).toContainText('Staying Home Still');

        // Remove the guest.
        page.once('dialog', (dialog) => dialog.accept());
        await page.getByRole('button', { name: 'Remove' }).click();
        await expect(page.locator('body')).toContainText('No guests added yet.');

        // Continuing from the hub resumes the wizard on the revealed group
        // step, which completes the run exactly like the plain flow.
        await page.getByRole('link', { name: 'Continue' }).click();
        await expect(page.locator('body')).toContainText('Are you registering a group for your organization?');
        await page.getByRole('radio', { name: 'No', exact: true }).click();
        await page.getByRole('button', { name: 'Register' }).click();

        await expect(page).toHaveURL(/\/registration\/admin$/);
        await expect(page.locator('.alert-success')).toContainText('Nothing was recorded.');
        await shot(page, testInfo, 'guest-registration-completed');
    });

    test('answering the trigger "No" never offers the hub', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/registration/admin/test-registration');

        await page.fill('input[name="ui_test_badge_name"]', 'Ada Lovelace');
        await page.getByRole('button', { name: 'Next' }).click();

        await page.getByRole('radio', { name: 'No', exact: true }).click();
        await page.getByRole('button', { name: 'Next' }).click();

        await page.getByRole('radio', { name: 'No', exact: true }).click();
        await page.getByRole('button', { name: 'Register' }).click();

        // Unchanged from the plain flow: straight to completion, no hub.
        await expect(page).toHaveURL(/\/registration\/admin$/);
        await expect(page.locator('.alert-success')).toContainText('Nothing was recorded.');
    });
});
