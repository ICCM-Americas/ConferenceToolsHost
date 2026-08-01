// Post-commit guest and group-member management on the registrant-facing "My
// Registration" page (see MyGuestController/MyGroupMemberController). Unlike
// guest-registration.spec.js/group-registration.spec.js, this drives a REAL
// registration to completion (through a throwaway, freshly signed-up host
// account) rather than the admin test-drive, since the feature under test
// only exists for an already-committed registration.
//
// Because the suite shares one database across every spec file in a run, this
// spec restores the "zero registrants" state (that registration-reports.spec.js
// and others depend on) via the admin dashboard's "Delete All Registration
// Answers" reset before it finishes — see its own comment below.
import { test, expect, shot, login, loginAsAdmin } from './support/helpers.js';

const EMAIL = 'playwright-mine-guest-test@example.com';
const PASSWORD = 'playwright-test-password';
const BADGE_NAME = 'Playwright Mine Tester';

async function logout(page) {
    await page.goto('/');
    await page.getByRole('button', { name: 'Log Out' }).click();
}

async function registerAndOpen(page) {
    await loginAsAdmin(page);

    // Idempotent: safe even if a config value is already set from another spec.
    await page.goto('/registration/admin/emails');
    await page.fill('#admin_email', 'registration-admin@example.com');
    await page.getByRole('button', { name: 'Save' }).first().click();

    await page.goto('/registration/admin');
    const openButton = page.getByRole('button', { name: 'Open Registration Now' });
    if (await openButton.isVisible()) {
        await openButton.click();
    }
}

async function signUpAndCompleteWizard(page) {
    await logout(page);
    await page.goto('/register');
    await page.fill('#email', EMAIL);
    await page.fill('#password', PASSWORD);
    await page.fill('#password_confirmation', PASSWORD);
    await page.click('button[type="submit"]');

    await page.goto('/registration/register');
    await page.fill('input[name="ui_test_badge_name"]', BADGE_NAME);
    await page.getByRole('button', { name: 'Next' }).click();

    // Guest and group triggers — "No" to both, so the wizard commits directly.
    await page.getByRole('radio', { name: 'No', exact: true }).click();
    await page.getByRole('button', { name: 'Next' }).click();
    await page.getByRole('radio', { name: 'No', exact: true }).click();
    await page.getByRole('button', { name: 'Register' }).click();
}

test.describe('post-commit guest and group-member management', () => {
    test('add, edit and remove a guest and a group member from My Registration, then gating hides both links', async ({ page }, testInfo) => {
        await registerAndOpen(page);
        await signUpAndCompleteWizard(page);

        await page.goto('/registration/mine');
        await expect(page.locator('body')).toContainText(BADGE_NAME);
        await shot(page, testInfo, 'mine-page-after-registering');

        // Guests: add, edit, remove — mirrors guest-registration.spec.js's
        // pre-commit flow, but against the real, committed Guest row.
        await page.getByRole('link', { name: 'Manage Non-Attending Guests' }).click();
        await expect(page).toHaveURL(/\/registration\/mine\/guests$/);
        await expect(page.locator('body')).toContainText('No guests added yet.');

        await page.getByRole('link', { name: 'Add a Guest' }).click();
        await page.getByLabel('Adult', { exact: true }).check();
        await page.fill('input[name="ui_test_guest_name"]', 'Stay Home');
        await page.getByRole('button', { name: 'Save Guest' }).click();

        await expect(page.locator('body')).toContainText('Stay Home');
        await shot(page, testInfo, 'mine-guests-hub-with-guest');

        await page.getByRole('link', { name: 'Edit' }).click();
        await page.fill('input[name="ui_test_guest_name"]', 'Staying Home Still');
        await page.getByRole('button', { name: 'Save Guest' }).click();
        await expect(page.locator('body')).toContainText('Staying Home Still');

        page.once('dialog', (dialog) => dialog.accept());
        await page.getByRole('button', { name: 'Remove' }).click();
        await expect(page.locator('body')).toContainText('No guests added yet.');

        await page.getByRole('link', { name: 'Continue' }).click();
        await expect(page).toHaveURL(/\/registration\/mine$/);

        // Group members: add, edit, remove — the leader's own solo group has
        // no other completed member to show read-only here, but the pending
        // invite's editable row is the golden path this feature adds; the
        // read-only "already registered" row is covered by the PHPUnit suite
        // (MyGroupMemberControllerTest).
        await page.getByRole('link', { name: 'Manage Group Members' }).click();
        await expect(page).toHaveURL(/\/registration\/mine\/group-members$/);
        await expect(page.locator('body')).toContainText('No group members added yet.');

        await page.getByRole('link', { name: 'Add a Group Member' }).click();
        await page.fill('input[name="group_member_name"]', 'Grace Hopper');
        await page.fill('input[name="group_member_email"]', 'grace@example.com');
        await page.getByRole('button', { name: 'Save Group Member' }).click();

        await expect(page.locator('body')).toContainText('Grace Hopper');
        await expect(page.locator('body')).toContainText('grace@example.com');
        await shot(page, testInfo, 'mine-group-members-hub-with-member');

        await page.getByRole('link', { name: 'Edit' }).click();
        await page.fill('input[name="group_member_name"]', 'Grace Hopper Jr');
        await page.getByRole('button', { name: 'Save Group Member' }).click();
        await expect(page.locator('body')).toContainText('Grace Hopper Jr');

        page.once('dialog', (dialog) => dialog.accept());
        await page.getByRole('button', { name: 'Remove' }).click();
        await expect(page.locator('body')).toContainText('No group members added yet.');

        // Gating: mark the registrant paid from the admin Payments console —
        // both management links (and Modify) disappear.
        await logout(page);
        await loginAsAdmin(page);
        await page.goto('/registration/admin/payments');
        const card = page.locator('.card', { hasText: BADGE_NAME });
        await card.locator('input[name="is_paid"]').check();
        await card.getByRole('button', { name: 'Save Payment' }).click();

        await logout(page);
        await login(page, { email: EMAIL, password: PASSWORD });

        await page.goto('/registration/mine');
        await expect(page.locator('body')).not.toContainText('Manage Non-Attending Guests');
        await expect(page.locator('body')).not.toContainText('Manage Group Members');
        await shot(page, testInfo, 'mine-page-gated-once-paid');

        // Restore the "zero registrants" state the rest of the suite depends
        // on (see registration-reports.spec.js) — this run's whole point was
        // a real, committed registration, which every other spec assumes
        // doesn't exist. Never deletes user accounts (see AnswerPurge).
        await logout(page);
        await loginAsAdmin(page);
        await page.goto('/registration/admin');
        page.once('dialog', (dialog) => dialog.accept());
        await page.getByRole('button', { name: 'Delete' }).click();
        await expect(page.locator('.alert-success')).toContainText('All registration answers have been deleted.');

        // Also clear the window this spec opened, back to the pristine "no
        // window scheduled" state — leaving it merely closed (rather than
        // unscheduled) would still leave Questions/Options locked (see
        // RegistrationStatus::answersLocked()) and would report the wrong
        // closed-page message ("has ended" instead of the generic closed
        // message) for whatever spec runs next.
        await page.fill('#opens_at', '');
        await page.fill('#closes_at', '');
        await page.getByRole('button', { name: 'Save Window' }).click();
        await expect(page.locator('.alert-success')).toContainText('The registration window has been saved.');

        // And the admin email this spec configured — registration-window.spec.js
        // depends on it starting unset, to exercise the "unconfigured" guard.
        await page.goto('/registration/admin/emails');
        await page.fill('#admin_email', '');
        await page.getByRole('button', { name: 'Save' }).first().click();
        await expect(page.locator('.alert-success')).toContainText('The addresses have been saved.');
    });
});
