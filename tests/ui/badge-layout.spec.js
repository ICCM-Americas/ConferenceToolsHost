// The badge layout designer: a page separate from the Name Badges report
// where an admin arranges badge name/logo/conference name/organization plus
// up to two static text blocks and two static images, independently per card
// size. The UI database seeds no registrants, so the badges report itself
// shows its empty state — this spec only exercises the designer page.
import { test, expect, shot, loginAsAdmin } from './support/helpers.js';

const PNG_1x1 = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC',
    'base64',
);

test.describe('badge layout designer', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/registration/admin/logistics/badges');
    });

    test('the badges report links to the layout designer', async ({ page }) => {
        await page.getByRole('link', { name: 'Edit Layout' }).click();
        await expect(page).toHaveURL(/\/registration\/admin\/logistics\/badges\/layout/);
        await expect(page.locator('h1')).toContainText('Badge Layout');
    });

    test('loading defaults, adding, dragging, and deleting elements', async ({ page }, testInfo) => {
        await page.getByRole('link', { name: 'Edit Layout' }).click();

        // A never-customized size starts empty and offers to load the defaults.
        await expect(page.locator('.alert-secondary')).toContainText('no custom layout yet');
        await page.getByRole('button', { name: 'Load Current Default Layout' }).click();
        await expect(page.locator('.badge-layout-element')).toHaveCount(4);
        await shot(page, testInfo, 'badge-layout-defaults-loaded');

        // Add a static text element (repeatable, up to two).
        await page.getByRole('button', { name: /^Static Text \(0\/2\)/ }).click();
        await expect(page.locator('.badge-layout-element')).toHaveCount(5);
        await expect(page.getByRole('button', { name: /^Static Text \(1\/2\)/ })).toBeVisible();

        // Fill in its text and save via the settings form.
        const textareas = page.locator('textarea[name="text"]');
        await textareas.last().fill('Sponsored by Acme');
        await page.locator('form:has(textarea[name="text"]) button[type="submit"]').last().click();
        await expect(page.locator('body')).toContainText('Sponsored by Acme');

        // Drag the Logo element (narrow enough to have real room to move,
        // unlike the full-width Badge Name/Organization elements) and confirm
        // its position changed.
        const box = page.locator('.badge-layout-element', { hasText: 'Logo' });
        const before = await box.evaluate((el) => el.style.left);
        const canvasBox = await page.locator('#badge-layout-canvas').boundingBox();
        const elBox = await box.boundingBox();
        await page.mouse.move(elBox.x + elBox.width / 2, elBox.y + elBox.height / 2);
        await page.mouse.down();
        await page.mouse.move(canvasBox.x + canvasBox.width * 0.6, canvasBox.y + canvasBox.height * 0.6, { steps: 10 });
        await page.mouse.up();
        await expect.poll(() => box.evaluate((el) => el.style.left)).not.toBe(before);
        await expect(page.locator('#badge-layout-status')).toContainText('Position saved');
        await shot(page, testInfo, 'badge-layout-dragged');

        // Delete an element.
        page.once('dialog', (dialog) => dialog.accept());
        await page.locator('form:has(button:text("Delete"))').first().locator('button:text("Delete")').click();
        await expect(page.locator('.badge-layout-element')).toHaveCount(4);
    });

    test('adding a static image via upload', async ({ page }, testInfo) => {
        // A distinct, still-untouched sheet size, so this test doesn't collide
        // with the previous test's changes to the default (avery_5392) layout.
        await page.goto('/registration/admin/logistics/badges/layout?size=avery_l4728');
        await page.getByRole('button', { name: /^Static Image \(0\/2\)/ }).click();

        await page.locator('input[type="file"][name="image"]').last().setInputFiles({
            name: 'sponsor.png',
            mimeType: 'image/png',
            buffer: PNG_1x1,
        });
        await page.locator('form:has(input[name="image"]) button[type="submit"]').last().click();

        await expect(page.locator('.card:has-text("Static Image") img')).toBeVisible();
        await shot(page, testInfo, 'badge-layout-image-uploaded');
    });

    test('each sheet size has its own independent layout', async ({ page }) => {
        // Two more untouched sizes, distinct from the ones the other tests customize.
        await page.goto('/registration/admin/logistics/badges/layout?size=avery_5371');
        await page.getByRole('button', { name: 'Load Current Default Layout' }).click();
        await expect(page.locator('.badge-layout-element')).toHaveCount(4);

        // Switching to another sheet size shows its own (still empty) layout.
        await page.locator('input[name="size"][value="avery_c32011"]').check();
        await expect(page.locator('.alert-secondary')).toContainText('no custom layout yet');
        await expect(page.locator('.badge-layout-element')).toHaveCount(0);
    });
});
