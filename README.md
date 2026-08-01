# Meeting Sessions

A Laravel application for **nominating, selecting, scheduling, and publishing meeting sessions**.

Attendees suggest topics, indicate which sessions they would attend and which they
would be "disappointed to miss", and the app uses that co-attendance data to
assign selected topics to rooms and time slots while minimizing conflicts.

The process runs through three **internal** phases — Nomination, Selection,
Results. **These phase names are visible to admins only.** Non-admin users simply
experience the right behavior for the current phase without ever seeing a phase
label.

---

## Architecture: scheduling lives in a local Composer package

All of the scheduling functionality (nomination, selection/preferences, permanent
sessions, rooms, time slots, phases, schedule generation, results, PDF/CSV
exports, archives, and the scheduler admin screens) now ships as a reusable
package, **`conference-tools/bof-scheduler`**, installed from a local path
repository at [`packages/conference-tools/bof-scheduler`](packages/conference-tools/bof-scheduler).

This host application keeps only what is genuinely host-specific:

- Authentication: login, logout, registration, password reset
- Passkeys, MFA (two-factor), the user profile
- User administration (lock/unlock, promote/demote, password resets, passkey/MFA
  reset)
- The global site layout (`resources/views/layouts/app.blade.php`)
- The `manage-scheduler` gate (in `app/Providers/AppServiceProvider.php`) that
  decides who may administer scheduling, and the post-login redirect into the
  scheduler (`app/Support/PhaseRedirector.php`)

The host consumes the package through Composer (`repositories: [{type: path}]`),
publishes `config/bofscheduler.php`, and layers its own middleware
(`not.locked`, `password.current`) onto the package routes via that config. The
scheduler's domain tables use a `bof_` prefix to avoid collisions. See the
[package README](packages/conference-tools/bof-scheduler/README.md) for full
integration, configuration, and customization details.

---

## Requirements

- PHP 8.3+ (developed on PHP 8.4)
- Composer 2
- SQLite (default) or MySQL/PostgreSQL
- The `gd` extension (used once to generate placeholder PWA icons; already bundled)

No asset build step is required. The host's own screens are styled by
`public/css/app.css`, served straight from the web root; the shared design
system and the package screens are styled by the stylesheets published in
step 5 below. Node is needed only to run the Playwright UI suite.

The UI ships no web fonts: every screen renders in the platform's own interface
font (`system-ui`, with the usual per-platform fallbacks), which keeps the
download small and the app looking native. The one bundled face is DejaVu Sans,
which the branding package streams from its font route into the client-side PDF
exports — those need an embeddable font with predictable metrics.

---

## Setup

```bash
# 1. Install dependencies
composer install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Database (SQLite by default)
touch database/database.sqlite
php artisan migrate

# 4. Storage symlink (for uploaded logos)
php artisan storage:link

# 5. Package stylesheets (into public/vendor/<pkg>/css)
php artisan vendor:publish --tag=laravel-assets --force

# 6. Run
php artisan serve
```

Step 5 is not optional: the conference-tools packages ship their CSS inside the
package, and every screen renders unstyled until it is published. Composer's
stock `post-update-cmd` re-runs it after `composer update`, and both deploy
paths run it before staging, so it is normally only needed by hand on a fresh
clone. See the [branding package's README](packages/conference-tools/branding/README.md)
for how the shared `iccm-*` design system is organized.

Migrating leaves the database with **no accounts at all**. That is deliberate:
nothing the application or either deploy path runs ever creates a user with a
password anyone else could know. The first admin is made by hand, once, per the
next section.

### Creating the first admin

1. Deploy and browse to the site, then **register normally through the UI** —
   the sign-up form, same as any other participant. Choose the address and
   password you want the organizer account to have; the app's password rule
   applies (minimum 12 characters, no composition requirements).
2. **Promote that account to admin with direct database access**, since nothing
   in the UI can grant the first admin flag. Use whatever gets you a SQL prompt
   on the deployment — `php artisan tinker`, the hosting panel's phpMyAdmin, or
   the `sqlite3` CLI.

The flag is the `is_admin` column on `users`, so the promotion is one statement:

```sql
UPDATE users SET is_admin = 1 WHERE email = 'you@your-conference.org';
```

SQLite (the default) stores it the same way, so the identical statement works
there:

```bash
sqlite3 database/database.sqlite \
  "UPDATE users SET is_admin = 1 WHERE email = 'you@your-conference.org';"
```

Confirm it took before logging out — `SELECT email, is_admin FROM users;`
should show `1` against your address. Log in again and the admin dashboard
(`/admin`) appears in the navigation; from there every further admin can be
created and promoted through **Admin → Users**, so this is a one-time step.

Where you have shell access on the deployment, `php artisan app:create-admin`
does the same job in one command and will promote an existing account if the
email already exists.

---

## Key concepts

### Phases (admin-only names)

`ConferenceTools\BoFScheduler\Enums\EventPhase` +
`ConferenceTools\BoFScheduler\Services\PhaseService` are the single source of
truth for "what phase are we in?". The rules, in order of precedence:

1. **Manual override** always wins.
2. Otherwise, if **automatic phase changes** are enabled, the phase is derived
   from the configured transition timestamps and the current server time in the
   configured time zone.
3. Otherwise it falls back to Nomination.

Configure everything under **Admin → Phases**. The admin dashboard shows the
effective phase, whether it is automatic or overridden, the next configured
transition, and **both server and browser time/zone** so time-zone problems are
easy to spot.

Phase-specific behavior is enforced **server-side** (middleware + form-request
`authorize()`), not merely hidden in the UI.

### Login redirects

After login users go straight to the phase-appropriate page (never a generic
dashboard):

- Nomination → suggestions list (`/bofs/sessions`)
- Selection → preference page (`/bofs/selection`)
- Results → authenticated results (`/bofs/my-results`)
- Admins → admin dashboard (`/admin`)

The `/bofs` prefix is the host's configured `route_prefix`; the package default
is `bof`.

### Capacity & selection (never "top 8")

`ConferenceTools\BoFScheduler\Services\CapacityService` computes capacity
dynamically:

```
available positions = (active usual rooms × active usual time slots)
                     − permanent sessions occupying a usual room/time-slot position
selectable nominated sessions = available positions
```

Permanent sessions in **special** rooms/times do **not** consume a generated
position. Assisted selection (`SelectionService`) ranks nominations by
`1 × would_attend + 2 × disappointed_to_miss` and selects the top *N* where *N*
is this dynamic capacity.

### Permanent sessions

A permanent session is always published and can use any combination of:

- usual room / usual time slot (consumes one generated position; a hard
  constraint during generation)
- special room / usual time slot
- usual room / special time
- special room / special time

Special placements still appear in all results/PDFs and still collect expected
attendance, and still create attendee conflicts when their time overlaps a usual
slot. Placement is stored as a **locked** `schedule_assignment`.

### Scheduling

`ConferenceTools\BoFScheduler\Contracts\ScheduleGeneratorInterface` →
`ConferenceTools\BoFScheduler\Services\GreedyScheduler`. The scheduler is
bound through an interface so the heuristic can be replaced by a stronger solver
later without touching controllers.

Approach: build available positions (excluding locked permanent ones) → sort
selected nominations by demand/conflict pressure → greedy least-penalty placement
→ pairwise-swap local search → put higher-demand sessions in larger rooms within
each slot. Scoring lives in
`ConferenceTools\BoFScheduler\Services\Scheduling\ScheduleScorer` (and the
conflict weighting in `ConflictMatrix`) so it can be unit-tested in isolation:

```
total score = conflict_penalty + room_size_penalty + unassigned_penalty   (lower is better)
```

Conflict weights follow the spec: same-slot pairs cost `+1` (both would-attend),
`+2` if either is disappointed, `+4` if both are; permanent attendance adds a
penalty weighted more for "definitely" than "probably".

### Merging (Nomination phase only)

Admins merge two or more duplicate suggestions into a **new** combined record,
editing the final title and description (pre-filled with a sensible combination).
The originals are never overwritten: each is preserved as a soft-deleted `merged`
row pointing at the new target, so the full history survives in the CSV export.
Preferences transfer to the new record with OR-semantics on the boolean flags,
duplicate user/session rows are avoided, and a `session_merge_records` audit row
is written per original.

**Why merge/delete are Nomination-only (UI + server enforced):** after the
Nomination phase, changing or removing topics can undermine attendees' confidence
in the process and makes the result harder to explain. The restriction is
enforced by the `phase:nomination` middleware, not just hidden buttons.

### Archives — strategy & rationale

Archiving is implemented with **batch IDs** (an `archive_id` foreign key on
sessions and preferences), **not** physical table renaming.

The admin experience is still year/slug based (e.g. `2026`,
`2026-annual-meeting`): creating an archive snapshots the active nominated
suggestions and all preference rows into a named/dated archive, then resets the
active tables — while preserving users, rooms, time slots, branding, phase
settings and permanent sessions. Previous archives can be listed and their
suggestion CSVs downloaded.

**Why batch IDs:** it delivers identical admin-facing behavior while completely
avoiding dynamic table-name SQL (no injection surface) and keeping the data
trivially queryable and exportable. Year and slug are still strictly validated
(`year` 2000–2100, `slug` `[A-Za-z0-9-]+`).

### Soft deletes & exports

Suggestions are **soft-deleted**, never destroyed (no UI purge action ships by
default). The suggestions CSV (`Admin → Suggestions → CSV`, or per archive)
includes active, selected, not-selected, deleted, archived and merged rows, with
original vs current title/description, submitter display name/id, timestamps,
merge target, and archive name. Cells are sanitized against spreadsheet formula
injection and the file carries a UTF-8 BOM for Excel compatibility.

---

## Authentication, passkeys, MFA

- **Password login**, registration, and self-service password reset are built on
  Laravel's auth primitives. Password rule: `Password::min(12)` — length only, no
  composition requirements — applied everywhere (registration, profile change,
  admin-set temporary passwords, reset).
- **Passkeys (WebAuthn)** use the official [`laravel/passkeys`](https://github.com/laravel/passkeys-server)
  package. Users manage their own passkeys from their profile; admins can remove a
  user's passkeys. The browser ceremony is handled by a small dependency-free
  client in `resources/views/partials/passkey-scripts.blade.php`.
- **MFA (TOTP)** uses `pragmarx/google2fa` (`App\Services\TwoFactorService`).
  Users enroll/confirm/disable from their profile and receive one-time recovery
  codes; admins can reset a user's MFA.
- **Admin-set temporary passwords** force `must_change_password`; the
  `password.current` middleware redirects such users to the change-password screen
  before they can use anything else.
- **Account locking**: locked users cannot log in (password *or* passkey), and a
  locked session is terminated by middleware.
- **Last active admin** is protected: you cannot demote, lock, or delete the last
  admin who can still sign in (`App\Support\LastAdminGuard`).

### Passkeys / HTTPS in local development

WebAuthn requires a **secure context**. `localhost` is treated as secure by
browsers, so `php artisan serve` (http://localhost:8000) works for passkeys.
If you use any other hostname you must serve over **HTTPS** (e.g. Valet/Herd with
TLS, or a local proxy). The relying-party id and allowed origins are derived from
`APP_URL`:

```env
APP_URL=http://localhost:8000
# Optional: pin a stable WebAuthn user-handle secret if you rotate APP_KEY
PASSKEYS_USER_HANDLE_SECRET=
```

See `config/passkeys.php` for `relying_party_id`, `allowed_origins`, and timeout.

---

## Branding & PWA

**Admin → Branding** sets the site/event name, logo (stored on the `public`
disk), and color scheme (primary, secondary, optional background and text).
These drive both public and authenticated pages.

A valid web app manifest is served at `/manifest.webmanifest`
(`App\Http\Controllers\ManifestController`) and linked from the layout along with
mobile/theme meta tags, so the site can be added to a phone's home screen. The
manifest uses the configured branding (name, short name, theme/background color,
and the logo as the icon when present; placeholder icons live in
`public/icons/`).

---

## PDF

**Admin → Schedule → Results PDF** produces **one page per room** — usual rooms
and the special rooms used by permanent sessions — with a readable table of time
slot (incl. date), title, description and facilitators, plus branding, followed
by one page per time slot for central posting.

Every PDF in the project is generated **in the browser**, with jsPDF/AutoTable
driven by the branding package's shared `branding::partials.pdf-scripts` helper.
The server ships no PDF renderer: `/bofs/admin/results/pdf`
(`ConferenceTools\BoFScheduler\Http\Controllers\Admin\ResultPdfController`)
returns the localized, pre-formatted page data as JSON, and
`bofscheduler::partials.results-pdf` lays it out client-side. That keeps the
deployment free of both system binaries and a copyleft-licensed renderer.

---

## Sessions / HTTP session driver

The domain "sessions" table (meeting topics) takes the `sessions` table name, so
the framework's HTTP session store is configured to use the **file** driver
(`SESSION_DRIVER=file`) rather than the database driver. See the note in the
users migration.

---

## Testing

```bash
php artisan test
```

The suite (PHPUnit) covers guests/authorization, phase-by-phase public-results
behavior, phase-name visibility, password & passkey & MFA login, lockout, forced
password change, password rules, nominations and preferences per phase, merge &
delete phase restrictions and merge preference combination, dynamic capacity,
schedule generation around permanent constraints, results grouping, PDF
per-room output, all admin user-management actions plus last-admin safeguards,
profile management, and CSV export / archive create-list-export.

---

## Route map

The host's own routes — authentication, profile, and the host admin area
(prefixed `admin.`, behind the `admin` middleware) — all live in
`routes/web.php`. Every scheduling route comes from the package's
`routes/web.php`, under the configured `route_prefix` / `route_name_prefix`.
Run `php artisan route:list` for the full list.
