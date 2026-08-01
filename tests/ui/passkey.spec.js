// Exercises the WebAuthn passkey client in resources/views/partials/passkey-scripts.blade.php
// (window.passkeyRegister / window.passkeyLogin), wired from the profile and
// login pages. A Chrome DevTools virtual authenticator stands in for real
// hardware so the register-then-sign-in round trip runs end to end.
import { test, expect, shot, loginAsUser } from './support/helpers.js';

async function addVirtualAuthenticator(page) {
    const client = await page.context().newCDPSession(page);
    await client.send('WebAuthn.enable');
    await client.send('WebAuthn.addVirtualAuthenticator', {
        options: {
            protocol: 'ctap2',
            transport: 'internal',
            hasResidentKey: true,
            hasUserVerification: true,
            isUserVerified: true,
            automaticPresenceSimulation: true,
        },
    });
}

/**
 * Register a passkey from the profile page, then sign out and sign back in with
 * it. Returns the POST /passkeys/login response and the navigation response of
 * the page the client redirects to afterwards.
 */
async function registerThenSignIn(page, context, testInfo) {
    await addVirtualAuthenticator(page);

    // 1. Register a passkey from the profile page.
    await loginAsUser(page);
    await page.goto('/profile');
    await page.fill('#passkey_name', 'UI Test Key');

    const storeResponse = page.waitForResponse(
        (r) => r.url().includes('/user/passkeys') && r.request().method() === 'POST',
    );
    await page.getByRole('button', { name: /passkey/i }).last().click();
    const store = await storeResponse;

    // The script redirects back to the profile, which now lists the passkey.
    await page.waitForURL(/\/profile$/);
    await expect(page.getByText('UI Test Key')).toBeVisible();
    await shot(page, testInfo, 'passkey-registered');

    // 2. Sign out (drop the session cookie; keep the authenticator) and sign back
    //    in using only the passkey.
    await context.clearCookies();

    const loginResponse = page.waitForResponse(
        (r) => r.url().includes('/passkeys/login') && r.request().method() === 'POST',
    );
    await page.goto('/login');
    await page.getByRole('button', { name: /passkey/i }).click();
    const login = await loginResponse;

    const landing = await page.waitForResponse((r) => r.request().isNavigationRequest());
    await shot(page, testInfo, 'passkey-signed-in');

    return { store, login, landing };
}

test.describe('passkeys (WebAuthn)', () => {
    test('register a passkey, authenticate, and land on a valid page', async ({ page, context }, testInfo) => {
        const { store, login, landing } = await registerThenSignIn(page, context, testInfo);

        // The full client round trip must be accepted by the server: the
        // attestation is stored and the later assertion is verified.
        expect(store.ok(), 'passkey registration rejected').toBeTruthy();
        expect(login.ok(), 'passkey login assertion rejected').toBeTruthy();

        // ...and the client must land the user on a real, authenticated page
        // rather than a dead redirect.
        expect(
            landing.status(),
            `passkey sign-in landed on ${landing.url()} with HTTP ${landing.status()}`,
        ).toBeLessThan(400);
    });

    test('sign-in failure surfaces an alert', async ({ page }) => {
        // No authenticator and no stored credential: navigator.credentials.get
        // rejects, and the client must surface the failure alert rather than
        // silently swallowing it.
        await page.goto('/login');

        const dialog = page.waitForEvent('dialog');
        await page.getByRole('button', { name: /passkey/i }).click();

        const alert = await dialog;
        expect(alert.message()).toMatch(/passkey/i);
        await alert.dismiss();
    });
});
