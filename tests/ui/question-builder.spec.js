// The question builder's section cards: the title is edited in place on the
// card header, which is also the section's drag handle — so the field has to
// take a click, a text selection and a save without Sortable stealing any of
// it.
import { test, expect, shot, loginAsAdmin } from './support/helpers.js';

test.describe('question builder sections', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
    });

    test('a section title is renamed in place on the builder', async ({ page, consoleErrors }, testInfo) => {
        await page.goto('/registration/admin/questions', { waitUntil: 'networkidle' });

        // The title field sits inside the card header that is also the section's
        // drag handle, so clicking into it must edit rather than start a drag.
        // The heading is a field now, so the card is found by position rather
        // than by its text.
        const header = page.locator('.section.js-badge-row').first().locator('.card-header');
        const field = header.locator('input[name="title"]');

        const rename = async (from, to) => {
            await expect(field).toHaveValue(from);
            await field.fill(to);
            await header.getByRole('button', { name: 'Save' }).click();
            await page.waitForURL(/#section-\d+$/);
        };

        await rename('UI Test Details', 'Renamed In Place');
        await expect(field).toHaveValue('Renamed In Place');
        await shot(page, testInfo, 'section-title-inline');

        // Leave the fixture data as the specs that read this step's heading expect it.
        await rename('Renamed In Place', 'UI Test Details');
        expect(consoleErrors).toEqual([]);
    });

    test('selecting text in the title field does not start a section drag', async ({ page, consoleErrors }) => {
        await page.goto('/registration/admin/questions', { waitUntil: 'networkidle' });

        const card = page.locator('.section.js-badge-row').first();
        const field = card.locator('input[name="title"]');
        const order = await page.locator('.section.js-badge-row').evaluateAll((cards) => cards.map((c) => c.dataset.sectionId));

        // Press inside the field and sweep across it: Sortable filters the form
        // out of its handle, so this selects text instead of dragging the card.
        const box = await field.boundingBox();
        await page.mouse.move(box.x + 8, box.y + box.height / 2);
        await page.mouse.down();
        await page.mouse.move(box.x + box.width - 8, box.y + box.height / 2, { steps: 10 });
        await page.mouse.up();

        await expect(field).toBeFocused();
        expect(await field.evaluate((el) => el.selectionEnd > el.selectionStart)).toBe(true);
        // No drop happened, so the builder never posted a new order.
        await expect(page.locator('#builder-status')).toBeEmpty();
        expect(await page.locator('.section.js-badge-row').evaluateAll((cards) => cards.map((c) => c.dataset.sectionId))).toEqual(order);
        expect(consoleErrors).toEqual([]);
    });
});
