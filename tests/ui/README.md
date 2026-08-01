# Black-box UI / JavaScript tests

End-to-end browser tests that drive a **real Chromium** against a **running copy
of the app** and exercise the client-side JavaScript that the PHPUnit suite never
touches. These are intentionally separate from `tests/Feature` and `tests/Unit`:
they make no assumptions about the internals, log in through the real forms, and
verify what a user actually sees.

Built with [Playwright](https://playwright.dev). Every test captures a screenshot
(for **passes and failures**) so a run can be eyeballed; failures additionally
keep a trace and video.

## Running

```bash
npm install            # first time only
npx playwright install chromium   # first time only — downloads the browser
npm run test:ui        # run the whole suite (headless)
npm run test:ui:headed # watch it in a real browser window
npm run test:ui:report # open the HTML report from the last run
```

You do **not** need a server running first. Playwright boots one for you (see
`webServer` in `playwright.config.js`): it rebuilds an **isolated** sqlite
database (`database/ui-testing.sqlite`) from scratch, seeds deterministic
fixtures, then serves the app on `http://localhost:8123`. Your dev `.env`
database is never touched. `localhost` (not `127.0.0.1`) is used so the WebAuthn
passkey test has a valid relying-party id.

## Where to look after a run

- `tests/ui/screenshots/` — one named, full-page PNG per checkpoint (e.g.
  `branding-theme-applied.png`, `selection-willing-enabled.png`). **This is the
  folder for manual verification.**
- `tests/ui/.report/` — the HTML report (`npm run test:ui:report`), with every
  screenshot attached, plus traces/videos for any failure.
- `tests/ui/.results/` — raw artifacts (traces, videos). Git-ignored.

## What is covered

| Spec | UI / JavaScript under test |
| --- | --- |
| `smoke.spec.js` | Every reachable GET page (guest, user, admin) renders < 400 and throws **no** console/JS errors. Screenshots all of them. |
| `nav-menus.spec.js` | Header `details.menu` dropdowns — open on hover, close on mouse-leave, toggle by keyboard (`layouts/app.blade.php`). |
| `branding-preview.spec.js` | Branding live preview — theme select rewrites color pickers + swatches, hand-editing a color drops to "Custom", swatch buttons apply a theme, logo file shows a local preview (`admin/branding.blade.php`). |
| `selection-willing.spec.js` | "Willing to facilitate" checkbox enables only when interest is expressed, and clears when withdrawn (scheduler `selection/index.blade.php`). |
| `sessions-facilitators.spec.js` | Facilitator picker appends the chosen candidate to the text box and de-duplicates (scheduler `admin/sessions/edit.blade.php`). |
| `dashboard-clock.spec.js` | Browser-local time/zone injected client-side on the scheduler admin dashboard. |
| `passkey.spec.js` | WebAuthn register-then-sign-in round trip via a virtual authenticator, plus the failure-alert path (`partials/passkey-scripts.blade.php`). |

## Fixtures

`database/seeders/UiTestingSeeder.php` builds the two fixed accounts
(`admin@example.com` / `user@example.com`, password `password-please-change`),
the package baseline data, one nominated BoF owned by the demo user, an
interested preference, and pins the event to the **Selection** phase. It is used
only by this suite — the application ships no seeded accounts, so these known
passwords exist nowhere a deployment can reach.
