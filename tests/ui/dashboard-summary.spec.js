// The registration admin dashboard's Registrations and Arrivals cards, and
// the room-assignment/Prayer-Pals coverage warnings (DashboardSummary /
// DashboardController). The UI database seeds no registrants (see
// prayer-pals.spec.js), so this spec covers the zero-data rendering; the
// PHPUnit feature suite (DashboardSummaryTest, DashboardControllerTest)
// covers the figures themselves with real data.
import { test, expect, shot, loginAsAdmin } from './support/helpers.js';

test.describe('registration admin dashboard summary', () => {
    test('the registrations card shows headcounts and the arrivals card shows none yet', async ({ page }, testInfo) => {
        await loginAsAdmin(page);
        await page.goto('/registration/admin');

        const registrations = page.locator('.card-body', { hasText: 'Registrations' }).first();
        await expect(registrations).toContainText('Attendees');
        await expect(registrations).toContainText('Guests');
        await expect(registrations).toContainText('Special Needs');
        await expect(registrations).toContainText('Shuttle Runs');

        await expect(page.locator('.card-body', { hasText: 'Arrivals' })).toContainText('No arrival-day answers yet.');
        await shot(page, testInfo, 'dashboard-summary-empty');

        // No one is registered, so there is nothing to warn about.
        await expect(page.locator('.alert-danger', { hasText: 'still need' })).toHaveCount(0);
    });
});
