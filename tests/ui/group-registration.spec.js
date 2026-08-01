// The group-registration flow: answering "yes" to the group question detours
// to a Group Member hub where a leader can add, edit and remove the people
// they're inviting before continuing the wizard. Driven against the admin
// test drive (like test-registration.spec.js) so nothing is actually
// recorded — no invite emails are ever sent for a test run.
//
// The group trigger is the wizard's last step in this fixture (nothing is
// configured after it — see UiTestingSeeder), so answering "Yes" and
// submitting detours to the hub even though there's no "next" wizard step;
// continuing from the hub resumes on that same (already "Yes") step, whose
// button reads "Register" again to finally commit.
import { test, expect, shot, loginAsAdmin } from './support/helpers.js';

test.describe('group registration', () => {
    test('answering the group trigger detours to the hub to add, edit and remove a member, then finishes', async ({ page }, testInfo) => {
        await loginAsAdmin(page);
        await page.goto('/registration/admin/test-registration');

        // Step 1: the ordinary batched questions.
        await page.fill('input[name="ui_test_badge_name"]', 'Ada Lovelace');
        await page.getByRole('button', { name: 'Next' }).click();

        // Step 2: the guest trigger — "No" so only the group flow is exercised.
        await expect(page.locator('body')).toContainText('Are you registering one or more non-attending guests?');
        await page.getByRole('radio', { name: 'No', exact: true }).click();
        await page.getByRole('button', { name: 'Next' }).click();

        // Step 3: the group trigger — the wizard's last step, so its button
        // already reads "Register"; answering "Yes" still detours to the hub.
        await expect(page.locator('body')).toContainText('Are you registering a group for your organization?');
        await page.getByRole('radio', { name: 'Yes', exact: true }).click();
        await page.getByRole('button', { name: 'Register' }).click();

        await expect(page).toHaveURL(/\/registration\/admin\/test-registration\/group-members$/);
        await expect(page.locator('body')).toContainText('Group Members');
        await expect(page.locator('body')).toContainText('No group members added yet.');
        await shot(page, testInfo, 'group-member-hub-empty');

        // Add a group member.
        await page.getByRole('link', { name: 'Add a Group Member' }).click();
        await page.fill('input[name="group_member_name"]', 'Grace Hopper');
        await page.fill('input[name="group_member_email"]', 'grace@example.com');
        await shot(page, testInfo, 'group-member-form');
        await page.getByRole('button', { name: 'Save Group Member' }).click();

        // Back on the hub: the member is listed with their email.
        await expect(page).toHaveURL(/\/registration\/admin\/test-registration\/group-members$/);
        await expect(page.locator('body')).toContainText('Grace Hopper');
        await expect(page.locator('body')).toContainText('grace@example.com');
        await shot(page, testInfo, 'group-member-hub-with-member');

        // Edit the member.
        await page.getByRole('link', { name: 'Edit' }).click();
        await expect(page.locator('input[name="group_member_name"]')).toHaveValue('Grace Hopper');
        await page.fill('input[name="group_member_name"]', 'Grace Hopper Jr');
        await page.getByRole('button', { name: 'Save Group Member' }).click();
        await expect(page.locator('body')).toContainText('Grace Hopper Jr');

        // Remove the member.
        page.once('dialog', (dialog) => dialog.accept());
        await page.getByRole('button', { name: 'Remove' }).click();
        await expect(page.locator('body')).toContainText('No group members added yet.');

        // Continuing from the hub resumes on the same (still "Yes") group
        // step, which finishes the run when submitted again.
        await page.getByRole('link', { name: 'Continue' }).click();
        await expect(page.locator('body')).toContainText('Are you registering a group for your organization?');
        await page.getByRole('button', { name: 'Register' }).click();

        await expect(page).toHaveURL(/\/registration\/admin$/);
        await expect(page.locator('.alert-success')).toContainText('Nothing was recorded.');
        await shot(page, testInfo, 'group-registration-completed');
    });

    test('answering the group trigger "No" never offers the hub', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/registration/admin/test-registration');

        await page.fill('input[name="ui_test_badge_name"]', 'Ada Lovelace');
        await page.getByRole('button', { name: 'Next' }).click();

        await page.getByRole('radio', { name: 'No', exact: true }).click();
        await page.getByRole('button', { name: 'Next' }).click();

        await page.getByRole('radio', { name: 'No', exact: true }).click();
        await page.getByRole('button', { name: 'Register' }).click();

        await expect(page).toHaveURL(/\/registration\/admin$/);
        await expect(page.locator('.alert-success')).toContainText('Nothing was recorded.');
    });

    test('answering yes to both guest and group reaches both hubs, in question order', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/registration/admin/test-registration');

        await page.fill('input[name="ui_test_badge_name"]', 'Ada Lovelace');
        await page.getByRole('button', { name: 'Next' }).click();

        // Guest is positioned before group (see UiTestingSeeder), so it
        // detours first even though group will also be answered "Yes".
        await page.getByRole('radio', { name: 'Yes', exact: true }).click();
        await page.getByRole('button', { name: 'Next' }).click();
        await expect(page).toHaveURL(/\/registration\/admin\/test-registration\/guests$/);

        // Continuing reaches the group step next, not the guest step again.
        await page.getByRole('link', { name: 'Continue' }).click();
        await expect(page.locator('body')).toContainText('Are you registering a group for your organization?');
        await page.getByRole('radio', { name: 'Yes', exact: true }).click();
        await page.getByRole('button', { name: 'Register' }).click();

        await expect(page).toHaveURL(/\/registration\/admin\/test-registration\/group-members$/);
    });
});
