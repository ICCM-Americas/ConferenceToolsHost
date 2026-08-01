// Exercises the header navigation dropdowns wired up by the inline script at the
// end of resources/views/layouts/app.blade.php: admin "details.menu" panels that
// open on hover (in addition to the native click/tap toggle).
import { test, expect, shot, loginAsAdmin } from './support/helpers.js';

test.describe('admin navigation menus', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/admin');
    });

    test('open on hover and close on mouse-leave', async ({ page }, testInfo) => {
        const menu = page.locator('nav.iccm-app details.iccm-menu').first();
        const summary = menu.locator('summary');

        await expect(menu).not.toHaveJSProperty('open', true);

        await summary.hover();
        await expect(menu).toHaveJSProperty('open', true);
        await expect(menu.locator('.iccm-menu-items')).toBeVisible();
        await shot(page, testInfo, 'nav-menu-open');

        // Move the pointer away from the menu to trigger mouseleave.
        await page.mouse.move(5, 5);
        await expect(menu).toHaveJSProperty('open', false);
    });

    test('keyboard toggles the menu (touch/keyboard path)', async ({ page }) => {
        // Keyboard activation exercises the native <details> toggle without the
        // mouse movement that would also fire the hover handlers.
        const menu = page.locator('nav.iccm-app details.iccm-menu').first();
        const summary = menu.locator('summary');

        await summary.focus();
        await page.keyboard.press('Enter');
        await expect(menu).toHaveJSProperty('open', true);

        await page.keyboard.press('Enter');
        await expect(menu).toHaveJSProperty('open', false);
    });

    test('all three admin section menus are present', async ({ page }) => {
        await expect(page.locator('nav.iccm-app details.iccm-menu')).toHaveCount(3);
    });
});
