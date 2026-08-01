// The Prayer Pals console: every attendee split by sex, manually clustered
// into small groups by dragging (see PrayerPalsController). The UI database
// seeds no registrants, so this spec exercises the admin-configuration side
// only — the label style toggle and creating/deleting groups — the same way
// badge-layout.spec.js exercises its designer without any badge data.
import { test, expect, shot, loginAsAdmin } from './support/helpers.js';

test.describe('prayer pals console', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/registration/admin/logistics');
    });

    test('the logistics hub links to Prayer Pals', async ({ page }) => {
        await page.getByRole('link', { name: 'Prayer Pals', exact: true }).click();
        await expect(page).toHaveURL(/\/registration\/admin\/logistics\/prayer-pals$/);
        await expect(page.locator('h1')).toContainText('Prayer Pals');
    });

    test('creating and deleting groups, and switching between numbered and lettered labels', async ({ page }, testInfo) => {
        await page.getByRole('link', { name: 'Prayer Pals', exact: true }).click();

        // No registrants are seeded, so both sexes start with nothing to group.
        await expect(page.locator('body')).toContainText('Everyone is grouped.');

        // Numbered by default: the first two groups for Men are "Group 1" and "Group 2".
        const menColumn = page.locator('.prayer-pals-column').filter({
            has: page.getByRole('heading', { name: 'Men', exact: true }),
        });
        await menColumn.getByRole('button', { name: 'New Group' }).click();
        await expect(menColumn.locator('.prayer-pals-group')).toHaveCount(1);
        await expect(menColumn.locator('.prayer-pals-group').first()).toContainText('Group 1');

        await menColumn.getByRole('button', { name: 'New Group' }).click();
        await expect(menColumn.locator('.prayer-pals-group')).toHaveCount(2);
        await expect(menColumn.locator('.prayer-pals-group').nth(1)).toContainText('Group 2');
        await shot(page, testInfo, 'prayer-pals-groups-numbered');

        // An empty group is flagged as needing more people.
        await expect(menColumn.locator('.prayer-pals-group').first()).toContainText('needs 3 more');

        // Switching to lettered labels relabels every existing group live.
        await page.selectOption('#label_style', 'letter');
        await expect(menColumn.locator('.prayer-pals-group').first()).toContainText('Group A');
        await expect(menColumn.locator('.prayer-pals-group').nth(1)).toContainText('Group B');
        await shot(page, testInfo, 'prayer-pals-groups-lettered');

        // Deleting the first group closes the gap: the remaining group becomes "A".
        page.once('dialog', (dialog) => dialog.accept());
        await menColumn.locator('.prayer-pals-group').first().getByRole('button', { name: 'Delete Group' }).click();
        await expect(menColumn.locator('.prayer-pals-group')).toHaveCount(1);
        await expect(menColumn.locator('.prayer-pals-group').first()).toContainText('Group A');
    });
});
