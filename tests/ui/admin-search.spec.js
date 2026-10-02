// The registration admin Search page: the toolbar button, the category
// toggles, bolded matches, results linking to where they're edited (a rule
// result opens its editor in the shared modal on the search page itself),
// per-category paging, and the invalid-regex error. Answer hits and deleting
// registrations are covered by the PHPUnit feature tests: the UI fixtures
// have no registrants by design.
import { test, expect, shot, loginAsAdmin } from './support/helpers.js';

const card = (page, category) => page.locator(`.js-search-results[data-category="${category}"]`);

test.describe('admin search', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
    });

    test('is reached from the toolbar with nothing checked, and category boxes toggle their row', async ({ page, consoleErrors }, testInfo) => {
        await page.goto('/registration/admin');
        await page.locator('nav.iccm-toolbar').getByRole('link', { name: 'Search', exact: true }).click();
        await page.waitForURL('**/registration/admin/search');

        await expect(page.locator('input[type="checkbox"]:checked')).toHaveCount(0);

        await page.locator('#search-category-questions').check();
        for (const place of ['question_text', 'options', 'question_rules']) {
            await expect(page.locator(`#search-in-${place}`)).toBeChecked();
        }
        await expect(page.locator('#search-translations')).not.toBeChecked();

        await page.locator('#search-in-options').uncheck();
        await expect(page.locator('#search-category-questions')).toHaveJSProperty('indeterminate', true);
        await shot(page, testInfo, 'search-form');
        expect(consoleErrors).toEqual([]);
    });

    for (const [name, width] of [['desktop', 1280], ['phone', 375]]) {
        test(`lays out every choice on one line beside its box at ${name} width`, async ({ page, consoleErrors }, testInfo) => {
            await page.setViewportSize({ width, height: 900 });
            await page.goto('/registration/admin/search');

            const labels = page.locator('form .iccm-checkbox-row');
            await expect(labels).toHaveCount(14);
            for (const label of await labels.all()) {
                await expect(label).toBeVisible();
                const box = await label.locator('input').boundingBox();
                const row = await label.boundingBox();
                // One line of text, with the box vertically centered on it.
                expect(row.height).toBeLessThan(36);
                expect(Math.abs((box.y + box.height / 2) - (row.y + row.height / 2))).toBeLessThan(3);
                expect(box.x - row.x).toBeLessThan(2);
            }

            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            expect(overflow).toBeLessThanOrEqual(0);
            await shot(page, testInfo, `search-form-${name}`);
            expect(consoleErrors).toEqual([]);
        });
    }

    test('bolds each match, and a question hit\'s editor returns to the results', async ({ page, consoleErrors }, testInfo) => {
        await page.goto('/registration/admin/search?q=pass+type&in[]=question_text');

        const hit = card(page, 'questions').locator('.list-group-item', { hasText: 'ui_test_pass' }).first();
        await expect(hit.locator('.reg-search-snippet strong')).toHaveText('Pass Type');
        await shot(page, testInfo, 'search-results');

        const results = page.url();
        await hit.getByRole('link', { name: 'Pass Type' }).click();
        await page.waitForURL(/\/registration\/admin\/questions\/\d+\/edit\?_return=/);

        // Both ways out of the editor lead back to these results.
        await page.getByRole('link', { name: 'Cancel' }).click();
        await page.waitForURL(results);
        await hit.getByRole('link', { name: 'Pass Type' }).click();
        await page.getByRole('button', { name: 'Save' }).click();
        await page.waitForURL(results);
        expect(consoleErrors).toEqual([]);
    });

    test('a rule hit opens its visibility editor in a modal on the search page', async ({ page, consoleErrors }, testInfo) => {
        await page.goto('/registration/admin/search?q=ui_test_pass&in[]=question_rules', { waitUntil: 'networkidle' });
        const url = page.url();

        const hit = card(page, 'questions').locator('.list-group-item').first();
        await expect(hit).toContainText('Section rule');
        await hit.locator('a.js-editor-link').first().click();

        const modal = page.locator('#editor-modal');
        await expect(modal.locator('.js-editor')).toBeVisible();
        await expect(modal.locator('.modal-body strong')).toHaveText('Guest & Group Registration');
        expect(page.url()).toBe(url);
        await shot(page, testInfo, 'search-rule-modal');

        await modal.locator('button.close').click();
        await expect(modal).toBeHidden();
        expect(consoleErrors).toEqual([]);
    });

    test('pages each category on its own, keeping the search', async ({ page, consoleErrors }) => {
        await page.goto('/registration/admin/search?q=.&regex=1&in[]=question_text&in[]=options');

        const questions = card(page, 'questions');
        await expect(questions.locator('.list-group-item')).toHaveCount(25);
        await questions.locator('.pagination').getByRole('link', { name: '2', exact: true }).click();

        await expect(page).toHaveURL(/questions_page=2/);
        await expect(page).toHaveURL(/regex=1/);
        await expect(page.locator('#search-q')).toHaveValue('.');
        await expect(card(page, 'questions').locator('.list-group-item').first()).toBeVisible();
        expect(consoleErrors).toEqual([]);
    });

    test('reports an invalid regular expression and an empty Answers card', async ({ page, consoleErrors }) => {
        await page.goto('/registration/admin/search?q=(pass&regex=1&in[]=question_text');
        await expect(page.locator('.invalid-feedback')).toContainText('Invalid regular expression: missing closing parenthesis');
        await expect(page.locator('.js-search-results')).toHaveCount(0);

        await page.goto('/registration/admin/search?q=zz-no-such-answer&in[]=answers&in[]=drafts');
        await expect(card(page, 'answers').locator('h2')).toHaveText('Answers (0)');
        await expect(card(page, 'answers')).toContainText('No matches.');
        expect(consoleErrors).toEqual([]);
    });
});
