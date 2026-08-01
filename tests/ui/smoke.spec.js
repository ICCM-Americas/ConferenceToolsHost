// Black-box page smoke test: every reachable GET screen must render with a 2xx
// response and without throwing client-side JavaScript errors. A screenshot of
// each page is captured for manual review.
import { test, expect, shot, loginAsAdmin, loginAsUser } from './support/helpers.js';

const GUEST_PAGES = [
    ['login', '/login'],
    ['register', '/register'],
    ['forgot-password', '/forgot-password'],
    ['two-factor-challenge', '/two-factor-challenge'],
];

const USER_PAGES = [
    ['home', '/'],
    ['profile', '/profile'],
    ['profile-mfa', '/profile/mfa'],
    ['bofs-selection', '/bofs/selection'],
    ['bofs-sessions', '/bofs/sessions'],
];

const ADMIN_PAGES = [
    ['admin-dashboard', '/admin'],
    ['admin-settings', '/admin/settings'],
    ['admin-branding', '/admin/branding'],
    ['admin-users', '/admin/users'],
    ['admin-users-create', '/admin/users/create'],
    ['profile', '/profile'],
    ['profile-mfa', '/profile/mfa'],
    ['bofs-admin', '/bofs/admin'],
    ['bofs-admin-sessions', '/bofs/admin/sessions'],
    ['bofs-admin-rooms', '/bofs/admin/rooms'],
    ['bofs-admin-time-slots', '/bofs/admin/time-slots'],
    ['bofs-admin-schedule', '/bofs/admin/schedule'],
    ['bofs-admin-phase-settings', '/bofs/admin/phase-settings'],
    ['bofs-admin-permanent-sessions', '/bofs/admin/permanent-sessions'],
    ['registration-admin', '/registration/admin'],
    ['registration-admin-test', '/registration/admin/test-registration'],
    ['registration-admin-pricing', '/registration/admin/pricing'],
    ['registration-admin-payments', '/registration/admin/payments'],
    ['registration-admin-steps', '/registration/admin/steps'],
    ['registration-admin-steps-create', '/registration/admin/steps/create'],
    // Step 1 always exists: the UI database is seeded by InfoStepSeeder.
    ['registration-admin-steps-edit', '/registration/admin/steps/1/edit'],
    ['registration-admin-variables', '/registration/admin/variables'],
    ['registration-admin-reports', '/registration/admin/reports'],
    ['registration-admin-logistics', '/registration/admin/logistics'],
    ['registration-admin-emails', '/registration/admin/emails'],
    ['registration-admin-closed', '/registration/admin/closed-messages'],
    // The fixed message set always exists: the UI database is seeded by
    // ClosedMessageSeeder (via UiTestingSeeder), and keys are the identifiers.
    ['registration-admin-closed-edit', '/registration/admin/closed-messages/before_open/edit'],
];

function smokeVisit(label, path) {
    return async ({ page, consoleErrors }, testInfo) => {
        const response = await page.goto(path, { waitUntil: 'networkidle' });
        expect(response, `no response for ${path}`).not.toBeNull();
        expect(response.status(), `${path} returned ${response.status()}`).toBeLessThan(400);
        await shot(page, testInfo, `smoke-${label}`);
        expect(consoleErrors, `console errors on ${path}`).toEqual([]);
    };
}

test.describe('guest pages', () => {
    for (const [label, path] of GUEST_PAGES) {
        test(`guest: ${path}`, smokeVisit(`guest-${label}`, path));
    }
});

test.describe('signed-in user pages', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsUser(page);
    });

    for (const [label, path] of USER_PAGES) {
        test(`user: ${path}`, smokeVisit(`user-${label}`, path));
    }
});

test.describe('admin pages', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
    });

    for (const [label, path] of ADMIN_PAGES) {
        test(`admin: ${path}`, smokeVisit(`admin-${label}`, path));
    }
});
