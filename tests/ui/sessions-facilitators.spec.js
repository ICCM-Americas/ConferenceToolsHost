// Exercises the inline script in packages/.../resources/views/admin/sessions/edit.blade.php:
// choosing a facilitator candidate and clicking "add" appends the name to the
// free-text facilitator box, de-duplicating and resetting the picker.
import { test, expect, shot, loginAsAdmin } from './support/helpers.js';

test.describe('admin session edit: facilitator picker', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/bofs/admin/sessions');
        // Open the editor for the seeded "UI Test BoF".
        await page
            .locator('[data-session-row]', { hasText: 'UI Test BoF' })
            .getByRole('link', { name: 'Edit' })
            .click();
        await expect(page.locator('[data-facilitators]')).toBeVisible();
    });

    test('adding a candidate appends it and de-duplicates', async ({ page }, testInfo) => {
        const box = page.locator('[data-facilitators]');
        const select = page.locator('[data-facilitator-candidates]');
        const add = page.locator('[data-facilitator-add]');

        // The seeded interested user is offered as a candidate.
        const candidate = await select
            .locator('option')
            .evaluateAll((opts) => opts.map((o) => o.value).find((v) => v));
        expect(candidate, 'expected a facilitator candidate').toBeTruthy();

        await select.selectOption(candidate);
        await add.click();

        await expect(box).toHaveValue(new RegExp(candidate.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
        // The picker resets to the blank placeholder after adding.
        await expect(select).toHaveValue('');
        await shot(page, testInfo, 'facilitator-added');

        // Adding the same candidate again must not duplicate it.
        const before = await box.inputValue();
        await select.selectOption(candidate);
        await add.click();
        await expect(box).toHaveValue(before);
    });
});
