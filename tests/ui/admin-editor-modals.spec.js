// The translations and visibility editors open in a modal over the console:
// the editor fragment is fetched over AJAX, its forms submit over AJAX, and
// closing the modal updates the row's tags in place — no navigation, so the
// browser's Back button still leaves the console (the old full-page editors
// grew the history with every save).
import { test, expect, shot, loginAsAdmin } from './support/helpers.js';

test.describe('admin editor modals', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
    });

    test('visibility editor works in a modal and tags the question row on close', async ({ page, consoleErrors }, testInfo) => {
        // A page visited before the console: Back must return here at the end.
        await page.goto('/registration/admin/steps?via=modal-test');
        await page.goto('/registration/admin/questions', { waitUntil: 'networkidle' });

        const row = page.locator('.question.js-badge-row').first();
        await expect(row.locator('[data-badge="conditional"]')).toBeHidden();

        await row.getByRole('link', { name: 'Visibility' }).click();
        const modal = page.locator('#editor-modal');
        await expect(modal.locator('.js-editor')).toBeVisible();
        expect(page.url()).toMatch(/\/registration\/admin\/questions$/); // no navigation happened

        // Start a rule: an AJAX submit that swaps the editor in place.
        await modal.getByRole('button', { name: 'Add a Visibility Rule' }).click();
        await expect(modal.getByText('Shown only when the rule matches:')).toBeVisible();
        await shot(page, testInfo, 'visibility-modal');

        await modal.locator('button.close').click();
        await expect(modal).toBeHidden();
        await expect(row.locator('[data-badge="conditional"]')).toBeVisible();

        // The modal left no history entries: Back leaves the console.
        await page.goBack();
        await page.waitForURL('**/registration/admin/steps**');
        expect(page.url()).toContain('via=modal-test');
        expect(consoleErrors).toEqual([]);
    });

    test('section visibility editor hides, shows and rules the whole step', async ({ page, consoleErrors }, testInfo) => {
        await page.goto('/registration/admin/questions', { waitUntil: 'networkidle' });

        // The section card nests its question rows (badged themselves), so
        // aim at the card header, which holds the section's own badges/links.
        const header = page.locator('.section.js-badge-row').first().locator('.card-header');
        await expect(header.locator('[data-badge="hidden"]')).toBeHidden();
        await expect(header.locator('[data-badge="conditional"]')).toBeHidden();

        await header.getByRole('link', { name: 'Visibility' }).click();
        const modal = page.locator('#editor-modal');
        await expect(modal.locator('.js-editor')).toBeVisible();
        await expect(modal.getByText('Decide when this section — and with it every question on it — is shown:')).toBeVisible();

        // "Never show" maps to the section's enabled flag and overrides its
        // questions' own visibility; "Make Visible" clears it again.
        await modal.getByRole('button', { name: 'Never Show This section' }).click();
        await expect(modal.getByText('This section is never shown (always hidden).')).toBeVisible();

        await modal.getByRole('button', { name: 'Make Visible' }).click();
        await expect(modal.getByText('This section is always shown.')).toBeVisible();

        // A rule's conditions may test other sections' questions only; with a
        // single seeded section the picker offers nothing but its placeholder.
        await modal.getByRole('button', { name: 'Add a Visibility Rule' }).click();
        await expect(modal.getByText('Shown only when the rule matches:')).toBeVisible();
        await expect(modal.locator('select[name="question_id"] option')).toHaveCount(1);
        await shot(page, testInfo, 'section-visibility-modal');

        await modal.locator('button.close').click();
        await expect(modal).toBeHidden();
        await expect(header.locator('[data-badge="conditional"]')).toBeVisible();
        expect(consoleErrors).toEqual([]);
    });

    test('translations editor saves a locale in the modal and tags the step row on close', async ({ page, consoleErrors }, testInfo) => {
        await page.goto('/registration/admin/steps', { waitUntil: 'networkidle' });

        const row = page.locator('.step.js-badge-row').first();
        await expect(row.locator('[data-badge="translated"]')).toBeHidden();

        await row.getByRole('link', { name: 'Translations' }).click();
        const modal = page.locator('#editor-modal');
        await expect(modal.locator('.js-editor')).toBeVisible();

        // Add a French heading; the refreshed fragment gains the locale card.
        await modal.locator('#new-locale').fill('fr');
        await modal.locator('#self-heading-new').fill('Connexion');
        await modal.getByRole('button', { name: 'Add', exact: true }).click();
        await expect(modal.locator('.card-header', { hasText: 'fr' })).toBeVisible();
        await shot(page, testInfo, 'translations-modal');

        await page.keyboard.press('Escape'); // the other close path
        await expect(modal).toBeHidden();
        await expect(row.locator('[data-badge="translated"]')).toBeVisible();
        expect(consoleErrors).toEqual([]);
    });

    test('option visibility editor opens from the question edit form and tags the option row', async ({ page, consoleErrors }, testInfo) => {
        // Options carry their own visibility rules, edited in the same shared
        // modal but opened from the option list on the question edit form.
        await page.goto('/registration/admin/questions', { waitUntil: 'networkidle' });
        await page.locator('.question.js-badge-row', { hasText: 'Pass Type' })
            .getByRole('link', { name: 'Edit' }).click();
        await page.waitForURL('**/edit');

        const optionRow = page.locator('.js-badge-row', { hasText: 'Full Pass' });
        await expect(optionRow.locator('[data-badge="conditional"]')).toBeHidden();

        await optionRow.getByRole('link', { name: 'Visibility' }).click();
        const modal = page.locator('#editor-modal');
        await expect(modal.locator('.js-editor')).toBeVisible();
        await expect(modal.getByText('Decide when this option is offered:')).toBeVisible();

        // Start a rule; options have no "never show" state (that is question-only).
        await modal.getByRole('button', { name: 'Add a Visibility Rule' }).click();
        await expect(modal.getByText('Shown only when the rule matches:')).toBeVisible();
        await expect(modal.getByRole('button', { name: 'Never Show This question' })).toBeHidden();
        await shot(page, testInfo, 'option-visibility-modal');

        await modal.locator('button.close').click();
        await expect(modal).toBeHidden();
        await expect(optionRow.locator('[data-badge="conditional"]')).toBeVisible();
        expect(consoleErrors).toEqual([]);
    });

    test('translations editor opens in the same modal from the step edit form', async ({ page, consoleErrors }) => {
        // The edit form hosts the same shared modal as the console list, so the
        // admin can translate without bouncing back to the list first.
        await page.goto('/registration/admin/steps', { waitUntil: 'networkidle' });
        await page.locator('.step.js-badge-row').first()
            .getByRole('link', { name: 'Edit' }).click();
        await page.waitForURL('**/edit');

        await page.getByRole('link', { name: 'Translations' }).click();
        const modal = page.locator('#editor-modal');
        await expect(modal.locator('.js-editor')).toBeVisible();

        // The AJAX submit round-trips against this form page just as on the list.
        await modal.locator('#new-locale').fill('de');
        await modal.locator('#self-heading-new').fill('Anmeldung');
        await modal.getByRole('button', { name: 'Add', exact: true }).click();
        await expect(modal.locator('.card-header', { hasText: 'de' })).toBeVisible();

        await modal.locator('button.close').click();
        await expect(modal).toBeHidden();
        expect(consoleErrors).toEqual([]);
    });
});
