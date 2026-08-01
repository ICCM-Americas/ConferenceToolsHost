// Narrow-viewport regression test for the builder-style admin lists (the
// registration form builder and the landing-page steps): each LOGICAL row (a
// question/step with its labels and buttons) may wrap onto several PHYSICAL
// rows on a phone-sized screen instead of forcing one overflowing line, so
// the page must never scroll horizontally. Separators (list-group borders)
// exist only between the logical rows.
import { test, expect, shot, loginAsAdmin } from './support/helpers.js';

const PAGES = [
    ['registration-steps', '/registration/admin/steps'],
    ['registration-questions', '/registration/admin/questions'],
];

test.use({ viewport: { width: 375, height: 812 } });

test.describe('narrow-screen admin lists', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
    });

    for (const [label, path] of PAGES) {
        test(`no horizontal overflow: ${path}`, async ({ page }, testInfo) => {
            await page.goto(path, { waitUntil: 'networkidle' });
            await shot(page, testInfo, `responsive-${label}-narrow`);

            const overflow = await page.evaluate(() => ({
                scrollWidth: document.documentElement.scrollWidth,
                clientWidth: document.documentElement.clientWidth,
            }));
            expect(overflow.scrollWidth, `${path} scrolls horizontally`)
                .toBeLessThanOrEqual(overflow.clientWidth);
        });
    }
});
