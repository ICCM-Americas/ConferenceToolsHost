// Exercises the inline script in packages/.../resources/views/selection/index.blade.php:
// the "willing to facilitate" checkbox is only enabled once the user has
// expressed interest, and is cleared when interest is withdrawn.
import { test, expect, shot, loginAsUser } from './support/helpers.js';

test.describe('selection: willing-to-facilitate toggle', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsUser(page);
        await page.goto('/bofs/selection');
    });

    test('willing checkbox follows the interest choice', async ({ page }, testInfo) => {
        const row = page.locator('[data-session-row]').first();
        await expect(row).toBeVisible();

        const none = row.locator('[data-interest][value=""]');
        const wouldAttend = row.locator('[data-interest][value="WouldAttend"]');
        const willing = row.locator('[data-willing]');

        // No interest -> disabled and unchecked.
        await none.check();
        await expect(willing).toBeDisabled();
        await expect(willing).not.toBeChecked();
        await shot(page, testInfo, 'selection-willing-disabled');

        // Interest expressed -> enabled.
        await wouldAttend.check();
        await expect(willing).toBeEnabled();

        // The user opts in to facilitating.
        await willing.check();
        await expect(willing).toBeChecked();
        await shot(page, testInfo, 'selection-willing-enabled');

        // Withdrawing interest disables AND clears the willing flag.
        await none.check();
        await expect(willing).toBeDisabled();
        await expect(willing).not.toBeChecked();
    });
});
