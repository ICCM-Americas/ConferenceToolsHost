// Exercises the branding live-preview script in the conference-tools/branding
// package (packages/conference-tools/branding/resources/views/branding.blade.php):
// choosing a theme rewrites the color pickers and their swatches, hand-editing a
// color drops back to "Custom", the swatch buttons apply a theme, and selecting a
// logo file shows a local preview.
import { test, expect, shot, loginAsAdmin } from './support/helpers.js';

// 1x1 transparent PNG.
const PNG_1x1 = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC',
    'base64',
);

test.describe('branding live preview', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/admin/branding');
    });

    test('selecting a theme updates the color pickers and swatches', async ({ page }, testInfo) => {
        const select = page.locator('#theme');
        const primary = page.locator('#primary_color');

        // The concrete themes (everything except "custom").
        const slugs = await select
            .locator('option')
            .evaluateAll((opts) => opts.map((o) => o.value).filter((v) => v && v !== 'custom'));
        expect(slugs.length, 'expected at least one preset theme').toBeGreaterThan(0);

        // Snapshot all four color pickers for a given theme.
        const colorSnapshot = () =>
            page.$$eval('#primary_color, #secondary_color, #background_color, #text_color', (els) =>
                els.map((el) => el.value).join(','),
            );

        await select.selectOption(slugs[0]);
        await expect(select).toHaveValue(slugs[0]);

        // applyTheme() set the color inputs to valid hex values...
        await expect(primary).toHaveValue(/^#[0-9a-fA-F]{6}$/);
        // ...and syncAllSwatches() mirrored each value into the picker's swatch.
        await expect(primary).toHaveAttribute('style', /background/i);

        await shot(page, testInfo, 'branding-theme-applied');

        // Switching to a different preset rewrites the colors.
        if (slugs.length > 1) {
            const first = await colorSnapshot();
            await select.selectOption(slugs[1]);
            await expect.poll(colorSnapshot).not.toBe(first);
        }
    });

    test('hand-editing a color switches the select to Custom', async ({ page }) => {
        const select = page.locator('#theme');
        // Ensure we start on a non-custom theme.
        const themeSlug = await select
            .locator('option')
            .evaluateAll((opts) => opts.map((o) => o.value).find((v) => v && v !== 'custom'));
        await select.selectOption(themeSlug);

        // Color inputs only fire 'input' on real user interaction, so dispatch it.
        await page.locator('#primary_color').evaluate((el) => {
            el.value = '#123456';
            el.dispatchEvent(new Event('input', { bubbles: true }));
        });

        await expect(select).toHaveValue('custom');
    });

    test('a swatch button applies its theme', async ({ page }, testInfo) => {
        const swatch = page.locator('.theme-swatch').first();
        const slug = await swatch.getAttribute('data-theme');

        await swatch.click();
        await expect(page.locator('#theme')).toHaveValue(slug);
        await shot(page, testInfo, 'branding-swatch-applied');
    });

    test('choosing a logo file shows a local preview', async ({ page }, testInfo) => {
        await page.locator('#logo').setInputFiles({
            name: 'logo.png',
            mimeType: 'image/png',
            buffer: PNG_1x1,
        });

        const preview = page.locator('#logo-preview');
        await expect(preview).toBeVisible();
        await expect.poll(() => preview.evaluate((el) => el.src)).toMatch(/^blob:/);
        await expect(page.locator('#logo-source')).toContainText(/overrides the theme logo/i);
        await shot(page, testInfo, 'branding-logo-preview');
    });
});
