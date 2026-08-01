// The question form's Options list: each option is one row showing its value,
// label, cost and per-diem with "guests"/"conditional" tags. Clicking a row
// swaps in a single pipe-format edit box ("value | label | cost | per-diem
// days | guests | help text"); leaving it redraws the read view client-side;
// the whole set saves with the form in its on-screen order.
import { test, expect, shot, loginAsAdmin } from './support/helpers.js';

test.describe('question options editor', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
    });

    test('rows edit inline as pipe lines, redraw on blur, and save with the form', async ({ page, consoleErrors }, testInfo) => {
        await page.goto('/registration/admin/questions', { waitUntil: 'networkidle' });
        const passTypeRow = page.locator('.question.js-badge-row', { hasText: 'Options Editor Test' });
        const questionId = await passTypeRow.getAttribute('data-question-id');
        await passTypeRow.getByRole('link', { name: 'Edit' }).click();
        await page.waitForURL('**/edit');

        const row = page.locator('#options-list li', { hasText: 'Full Pass' });
        const input = row.locator('.option-line');
        await expect(input).toBeHidden();

        // Click the read view → the pipe-format edit box appears, prefilled.
        await row.locator('.option-display').click();
        await expect(input).toBeVisible();
        await expect(input).toHaveValue('full | Full Pass');

        // Relabel and price it; leaving the box redraws the read view.
        await input.fill('full | Full Pass Deluxe | 25');
        await input.blur();
        await expect(input).toBeHidden();
        await expect(row.locator('.option-display')).toContainText('Full Pass Deluxe');
        await expect(row.locator('.option-display')).toContainText('— 25.00');
        await shot(page, testInfo, 'option-row-redrawn');

        // A new row starts in edit mode; Enter commits the line (it must not
        // submit the form), which persists the option right away — the row
        // gains its Visibility button without waiting for the form save.
        await page.locator('#option-add').click();
        const newRow = page.locator('#options-list li').last();
        await newRow.locator('.option-line').fill('vip | VIP Pass | 100');
        await newRow.locator('.option-line').press('Enter');
        await expect(newRow.locator('.option-line')).toBeHidden();
        expect(page.url()).toMatch(/\/edit$/); // Enter did not submit the form
        await expect(newRow.getByRole('link', { name: 'Visibility' })).toBeVisible();

        await page.getByRole('button', { name: 'Save' }).click();
        // Save lands back on the question's own row, not the top of the list.
        await page.waitForURL('**/registration/admin/questions#question-' + questionId);

        // Re-open the form: the relabel and the addition both persisted.
        await page.locator('.question.js-badge-row', { hasText: 'Options Editor Test' })
            .getByRole('link', { name: 'Edit' }).click();
        await page.waitForURL('**/edit');
        await expect(page.locator('#options-list li', { hasText: 'Full Pass Deluxe' })).toBeVisible();
        await expect(page.locator('#options-list li', { hasText: 'VIP Pass' })).toBeVisible();
        expect(consoleErrors).toEqual([]);
    });

    test('placeholder and options sections follow the chosen type', async ({ page, consoleErrors }) => {
        await page.goto('/registration/admin/questions', { waitUntil: 'networkidle' });
        await page.locator('.question.js-badge-row', { hasText: 'Options Editor Test' })
            .getByRole('link', { name: 'Edit' }).click();
        await page.waitForURL('**/edit');

        // A choice type: options (and the translate-value checkbox nested
        // inside that same fieldset) apply; an in-field placeholder does not.
        await expect(page.locator('#options-group')).toBeVisible();
        await expect(page.locator('#translate_value')).toBeVisible();
        await expect(page.locator('#placeholder-group')).toBeHidden();

        // A text-entry type: the reverse.
        await page.locator('#type').selectOption('text');
        await expect(page.locator('#options-group')).toBeHidden();
        await expect(page.locator('#translate_value')).toBeHidden();
        await expect(page.locator('#placeholder-group')).toBeVisible();

        // The native date picker has no free text to hint at: neither applies.
        await page.locator('#type').selectOption('date');
        await expect(page.locator('#options-group')).toBeHidden();
        await expect(page.locator('#placeholder-group')).toBeHidden();
        expect(consoleErrors).toEqual([]);
    });

    // The read-only mode a locked question form renders in (every control
    // disabled while registration is open or any answer exists) is exercised
    // server-side, down to the exact rendered HTML, by the PHPUnit suite
    // (QuestionBuilderControllerTest's lock tests) — this suite's fixtures
    // never reach that state (no spec here submits a real, persisted
    // registration; the UI database seeds no window and no answers), so
    // there's nothing further for a browser-level test to add. See
    // registration-reports.spec.js for the same boundary drawn for reports.

    test('deleting a row removes the option on save', async ({ page, consoleErrors }) => {
        await page.goto('/registration/admin/questions', { waitUntil: 'networkidle' });
        const passTypeRow = page.locator('.question.js-badge-row', { hasText: 'Options Editor Test' });
        const questionId = await passTypeRow.getAttribute('data-question-id');
        await passTypeRow.getByRole('link', { name: 'Edit' }).click();
        await page.waitForURL('**/edit');

        const row = page.locator('#options-list li', { hasText: 'Day Pass' });
        await row.getByRole('button', { name: 'Delete' }).click();
        await expect(page.locator('#options-list li', { hasText: 'Day Pass' })).toHaveCount(0);

        await page.getByRole('button', { name: 'Save' }).click();
        await page.waitForURL('**/registration/admin/questions#question-' + questionId);

        await page.locator('.question.js-badge-row', { hasText: 'Options Editor Test' })
            .getByRole('link', { name: 'Edit' }).click();
        await page.waitForURL('**/edit');
        await expect(page.locator('#options-list li', { hasText: 'Day Pass' })).toHaveCount(0);
        expect(consoleErrors).toEqual([]);
    });

    test('cancel returns to the question\'s own row without submitting', async ({ page, consoleErrors }) => {
        await page.goto('/registration/admin/questions', { waitUntil: 'networkidle' });
        const passTypeRow = page.locator('.question.js-badge-row', { hasText: 'Options Editor Test' });
        const questionId = await passTypeRow.getAttribute('data-question-id');
        await passTypeRow.getByRole('link', { name: 'Edit' }).click();
        await page.waitForURL('**/edit');

        await page.locator('#label').fill('Should not save');
        await page.getByRole('link', { name: 'Cancel' }).click();
        await page.waitForURL('**/registration/admin/questions#question-' + questionId);

        await expect(page.locator('.question.js-badge-row', { hasText: 'Should not save' })).toHaveCount(0);
        expect(consoleErrors).toEqual([]);
    });
});
