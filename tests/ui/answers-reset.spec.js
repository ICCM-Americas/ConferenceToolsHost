// Data reset (registration admin dashboard): "Delete all registration
// answers" wipes every answer/group/draft/room-assignment so a fresh testing
// pass starts blank, without deleting any user account
// (registration::partials.admin-answers-card). The PHPUnit feature suite
// (AnswerPurgeTest) covers that existing answers/groups/drafts/room-assignments
// actually get wiped; this spec drives the button itself through a real
// browser (confirm dialog, request, flash message).
import { test, expect, shot, loginAsAdmin } from './support/helpers.js';

test.describe('registration data reset', () => {
    test('an admin deletes all registration answers from the dashboard', async ({ page }, testInfo) => {
        await loginAsAdmin(page);
        await page.goto('/registration/admin');

        const answersCard = page.locator('.card', { hasText: 'Delete All Registration Answers' });
        const deleteButton = answersCard.getByRole('button', { name: 'Delete', exact: true });
        await expect(deleteButton).toBeVisible();
        await expect(page.locator('.card-body', { hasText: 'Complete' }).first()).toContainText('0');
        await shot(page, testInfo, 'answers-reset-card');

        page.once('dialog', (dialog) => dialog.accept());
        await deleteButton.click();

        await expect(page.locator('.alert-success')).toContainText('All registration answers have been deleted.');
        await expect(page.locator('.card-body', { hasText: 'Complete' }).first()).toContainText('0');
        await shot(page, testInfo, 'answers-reset-done');
    });
});
