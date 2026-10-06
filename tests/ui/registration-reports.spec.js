// The registration Logistics console (the interactive consoles and the
// question nominations that feed them) and the admin-defined Reports: a
// report is a type (Registrant or Individual, fixed at creation) and a
// name/description plus question or built-in columns, per-column
// "Shown When" cell rules, and report-level row rules — all edited on the
// Reports pages and exported as CSV and client-side PDF. The UI database
// seeds no registrants, so the report pages show their empty states — the
// row derivation itself is covered by the package's PHPUnit suite.
import fs from 'node:fs';
import { test, expect, shot, loginAsAdmin } from './support/helpers.js';

// Exercises the whole client-side PDF pipeline end to end: the pinned
// jsPDF/AutoTable loads from unpkg, the DejaVu faces stream from the
// branding package's font route, and the page's generator builds and
// saves the document. Needs network access to unpkg — the same
// dependency the real export has. The choices (radio labels) answer the
// admin-defined report's PDF Options dialog; without them the page exports
// directly.
const exportPdf = async (page, path, filenamePattern, choices) => {
    await page.goto(path);
    const downloadPromise = page.waitForEvent('download');
    await page.getByRole('button', { name: 'Export PDF' }).click();
    if (choices) {
        const dialog = page.locator('#pdf-options-modal');
        await expect(dialog).toBeVisible();
        for (const choice of choices) {
            await dialog.getByLabel(choice).check();
        }
        await dialog.getByRole('button', { name: 'Export', exact: true }).click();
        await expect(dialog).toBeHidden();
    }
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toMatch(filenamePattern);

    const contents = fs.readFileSync(await download.path());
    expect(contents.subarray(0, 5).toString()).toBe('%PDF-');
    // Real output, not a stub: the embedded DejaVu faces alone are
    // far bigger than this.
    expect(contents.length).toBeGreaterThan(10000);

    return contents;
};

test.describe('registration logistics and reports', () => {
    test('the logistics hub saves settings and links the consoles', async ({ page, consoleErrors }, testInfo) => {
        await loginAsAdmin(page);

        // Reached from the admin nav like every other console page.
        await page.goto('/registration/admin');
        await page.getByRole('link', { name: 'Logistics' }).click();
        await expect(page).toHaveURL(/\/registration\/admin\/logistics$/);
        await expect(page.locator('h1')).toContainText('Logistics');
        await shot(page, testInfo, 'logistics-hub');

        // The four consoles are linked; room definitions stay under the
        // top-level "Lodging" nav item, only the assignments board is here.
        await expect(page.getByRole('link', { name: 'Rooms', exact: true })).toHaveCount(0);
        await expect(page.getByRole('link', { name: 'Name Badges', exact: true })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Room Assignments', exact: true })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Shuttle Schedule', exact: true })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Prayer Pals', exact: true })).toBeVisible();

        // Question droplists show only the key, never "label (key)".
        const badgeNameOption = page.locator('#report_badge_name_key option[value="ui_test_badge_name"]');
        await expect(badgeNameOption).toHaveText('ui_test_badge_name');

        // Nominate the seeded badge-name question and set the shuttle numbers;
        // the minors-get-badges toggle defaults off and round-trips.
        await page.selectOption('#report_badge_name_key', 'ui_test_badge_name');
        await page.fill('#shuttle_seats', '10');
        await page.fill('#shuttle_travel_minutes', '45');
        await expect(page.locator('#guest_minors_get_badges')).not.toBeChecked();
        await page.check('#guest_minors_get_badges');
        await page.getByRole('button', { name: 'Save', exact: true }).click();

        await expect(page.locator('.alert-success')).toContainText('The logistics settings have been saved.');
        await expect(page.locator('#report_badge_name_key')).toHaveValue('ui_test_badge_name');
        await expect(page.locator('#shuttle_seats')).toHaveValue('10');
        await expect(page.locator('#guest_minors_get_badges')).toBeChecked();

        // Every console renders with both exports — the PDF is generated
        // client-side (jsPDF), so Export PDF is a button wired to the page's
        // generator, and the CSV stays a server link.
        for (const [name, heading] of [
            ['Name Badges', 'Name Badges'],
            ['Room Assignments', 'Room Assignments'],
            ['Shuttle Schedule', 'Shuttle Schedule'],
            ['Prayer Pals', 'Prayer Pals'],
        ]) {
            await page.goto('/registration/admin/logistics');
            await page.getByRole('link', { name, exact: true }).click();
            await expect(page.locator('body')).toContainText(heading);
            await expect(page.getByRole('button', { name: 'Export PDF' })).toBeVisible();
            await expect(page.getByRole('link', { name: 'Export CSV' })).toBeVisible();
        }

        expect(consoleErrors).toEqual([]);
    });

    test('a matching answer control is disabled until its question is chosen, and a Radio question pairs a droplist', async ({ page, consoleErrors }, testInfo) => {
        await loginAsAdmin(page);

        await page.goto('/registration/admin/logistics');

        // Unset: with no question to derive a widget from, the Prayer Pals
        // opt-in answer renders as a plain (disabled) field.
        await expect(page.locator('#guest_prayer_pals_key')).toHaveValue('');
        await expect(page.locator('#guest_prayer_pals_opt_in_values')).toBeDisabled();

        // Choosing a question enables that field live, without a save
        // round-trip — swapping to a *different-kind* widget (here, plain
        // text to a Radio question's droplist) only reshapes the control
        // after Save reloads the page with the newly nominated question.
        await page.selectOption('#guest_prayer_pals_key', 'ui_test_guest_prayer_pals');
        await expect(page.locator('#guest_prayer_pals_opt_in_values')).toBeEnabled();
        await page.getByRole('button', { name: 'Save', exact: true }).click();
        await expect(page.locator('.alert-success')).toContainText('The logistics settings have been saved.');

        // After the reload, the Prayer Pals opt-in answer widgets to a
        // droplist of the now-nominated Radio question's options.
        await expect(page.locator('select#guest_prayer_pals_opt_in_values option[value="Yes"]')).toHaveText('Yes');
        await shot(page, testInfo, 'logistics-matching-answer');

        expect(consoleErrors).toEqual([]);
    });

    test('a report is defined end to end: columns, cell rules, row rules, view and delete', async ({ page, consoleErrors }, testInfo) => {
        await loginAsAdmin(page);

        // Reached from the admin nav; empty until a report is defined.
        await page.goto('/registration/admin');
        await page.getByRole('link', { name: 'Reports' }).click();
        await expect(page).toHaveURL(/\/registration\/admin\/reports$/);
        await expect(page.locator('h1')).toContainText('Reports');
        await expect(page.locator('body')).toContainText('No reports defined yet.');

        // Define the report, then continue straight into its editor.
        await page.getByRole('link', { name: 'New Report' }).click();
        await page.check('#report-type input[value="registrant"]');
        await page.fill('#report-name', 'UI Test Roster');
        await page.fill('#report-description', 'Created by the UI suite.');
        await page.fill('#report-header', 'UI Test Header');
        await page.fill('#report-footer', 'UI Test Footer');
        await page.check('#include_adult_guests');
        await page.getByRole('button', { name: 'Save', exact: true }).click();
        await expect(page.locator('h1')).toContainText('Edit Report');
        await expect(page.locator('.alert-success')).toContainText('The report has been saved.');

        // A built-in column with a heading override…
        await page.selectOption('#add-column-form select[name="source"]', 'field:badge_name');
        await page.fill('#add-column-form input[name="header"]', 'Name');
        await page.getByRole('button', { name: 'Add Column' }).click();
        await expect(page.locator('.js-badge-row', { hasText: 'badge_name' })).toContainText('Name');

        // …and a question column shown by option label. Every row's own
        // "Column" source dropdown lists every available source (including
        // "ui_test_pass" itself), so once a second row exists, filtering by
        // that text would match both rows — the row's own visible title
        // ("Pass Type", the question's label) is unambiguous.
        await page.selectOption('#add-column-form select[name="source"]', { label: 'ui_test_pass' });
        await page.selectOption('#add-column-form select[name="display"]', 'label');
        await page.getByRole('button', { name: 'Add Column' }).click();
        await expect(page.locator('.js-badge-row', { hasText: 'Pass Type' })).toBeVisible();
        await shot(page, testInfo, 'report-editor-columns');

        // The per-column "Shown When" cell rule opens in the shared modal and
        // tags the row on close.
        const questionRow = page.locator('.js-badge-row', { hasText: 'Pass Type' });
        await questionRow.getByRole('link', { name: 'Shown When' }).click();
        const modal = page.locator('#editor-modal');
        await expect(modal.locator('.js-editor')).toBeVisible();
        await modal.getByRole('button', { name: 'Add a Visibility Rule' }).click();
        await expect(modal.getByText('Shown only when the rule matches:')).toBeVisible();
        // The group's AND/OR toggle is also a select[name=operator]; aim at
        // the add-condition form specifically. Cell rules offer registrant and
        // guest questions, one select each; only the picked side's is enabled.
        const conditionForm = modal.locator('form').filter({ has: page.locator('select[name="question_id"]') });
        await expect(conditionForm.getByLabel('Registrant questions')).toBeChecked();
        await conditionForm.locator('select[name="question_id"]:not([disabled])').selectOption({ label: 'ui_test_pass' });
        await conditionForm.locator('select[name="operator"]').selectOption('equals');
        await conditionForm.locator('input[name="value"]').fill('day');
        await conditionForm.getByRole('button', { name: 'Add Condition' }).click();
        await expect(modal.locator('.list-group-item', { hasText: 'ui_test_pass' })).toBeVisible();
        await shot(page, testInfo, 'report-cell-rule-modal');
        await modal.locator('button.close').click();
        await expect(modal).toBeHidden();
        await expect(questionRow.locator('[data-badge="conditional"]')).toBeVisible();

        // The report-level row rule uses the same modal from its own card.
        const rulesCard = page.locator('.card.js-badge-row', { hasText: 'Row Rules' });
        await rulesCard.getByRole('link', { name: 'Edit Row Rules' }).click();
        await expect(modal.locator('.js-editor')).toBeVisible();
        await modal.getByRole('button', { name: 'Add a Visibility Rule' }).click();
        await expect(modal.getByText('Shown only when the rule matches:')).toBeVisible();
        await modal.locator('button.close').click();
        await expect(rulesCard.locator('[data-badge="conditional"]')).toBeVisible();

        // The report page renders (empty — no registrants seeded) with both
        // exports, and the PDF pipeline produces a real document.
        await page.goto('/registration/admin/reports');
        await page.getByRole('link', { name: 'UI Test Roster' }).click();
        await expect(page.locator('h1')).toContainText('UI Test Roster');
        await expect(page.locator('body')).toContainText('No one to list yet.');
        await expect(page.locator('body')).toContainText('UI Test Header');
        await expect(page.locator('body')).toContainText('UI Test Footer');
        await expect(page.getByRole('link', { name: 'Export CSV' })).toBeVisible();
        const reportUrl = page.url();

        // Export PDF first asks for the orientation and paper size,
        // preselecting portrait and the US locale's letter paper; Cancel
        // closes it unexported.
        await page.getByRole('button', { name: 'Export PDF' }).click();
        const optionsDialog = page.locator('#pdf-options-modal');
        await expect(optionsDialog).toBeVisible();
        await expect(optionsDialog.getByLabel('Portrait')).toBeChecked();
        await expect(optionsDialog.getByLabel('Landscape')).not.toBeChecked();
        await expect(optionsDialog.getByLabel('US Letter')).toBeChecked();
        await expect(optionsDialog.getByLabel('A4')).not.toBeChecked();
        await shot(page, testInfo, 'report-pdf-options');
        await optionsDialog.getByRole('button', { name: 'Cancel' }).click();
        await expect(optionsDialog).toBeHidden();

        const pdf = await exportPdf(page, reportUrl, /^ui-test-roster-\d{8}-\d{6}\.pdf$/, ['Landscape', 'A4']);
        // A4 landscape, in points.
        const [, width, height] = pdf.toString('latin1').match(/\/MediaBox \[0 0 ([\d.]+) ([\d.]+)\]/);
        expect([Math.round(width), Math.round(height)]).toEqual([842, 595]);

        // Deleting the report (confirmed) returns the list to its empty state.
        await page.goto('/registration/admin/reports');
        page.once('dialog', (dialog) => dialog.accept());
        await page.getByRole('button', { name: 'Delete', exact: true }).click();
        await expect(page.locator('.alert-success')).toContainText('The report has been deleted.');
        await expect(page.locator('body')).toContainText('No reports defined yet.');

        expect(consoleErrors).toEqual([]);
    });

    for (const [name, width] of [['desktop', 1280], ['phone', 375]]) {
        test(`an individual report's row rule picks guest questions through the toggle at ${name} width`, async ({ page, consoleErrors }, testInfo) => {
            await page.setViewportSize({ width, height: 900 });
            await loginAsAdmin(page);

            // The type is asked for up front: each radio sits at the start
            // of its row, and the long descriptions wrap without overflow.
            await page.goto('/registration/admin/reports/create');
            for (const label of await page.locator('#report-type .iccm-checkbox-row').all()) {
                const box = await label.locator('input').boundingBox();
                const row = await label.boundingBox();
                expect(box.x - row.x).toBeLessThan(2);
            }
            // The guest-inclusion checkboxes sit beside their labels, one line
            // on desktop; at phone width a wide fallback font (CI's DejaVu
            // Sans) wraps them, so only the alignment is checked there.
            const guestBoxes = page.locator('.iccm-checkbox-row').filter({ has: page.locator('input[type="checkbox"]') });
            await expect(guestBoxes).toHaveCount(2);
            for (const label of await guestBoxes.all()) {
                const box = await label.locator('input').boundingBox();
                const row = await label.boundingBox();
                if (name === 'desktop') {
                    expect(row.height).toBeLessThan(36);
                }
                expect(Math.abs((box.y + box.height / 2) - (row.y + row.height / 2))).toBeLessThan(3);
                expect(box.x - row.x).toBeLessThan(2);
            }
            await shot(page, testInfo, `report-type-${name}`);
            await page.check('#report-type input[value="individual"]');
            await page.fill('#report-name', `UI Individual ${name}`);
            await page.getByRole('button', { name: 'Save', exact: true }).click();
            await expect(page.locator('h1')).toContainText('Edit Report');
            await expect(page.locator('body')).toContainText('Individual');

            const rulesCard = page.locator('.card.js-badge-row', { hasText: 'Row Rules' });
            await rulesCard.getByRole('link', { name: 'Edit Row Rules' }).click();
            const modal = page.locator('#editor-modal');
            await expect(modal.locator('.js-editor')).toBeVisible();
            await modal.getByRole('button', { name: 'Add a Visibility Rule' }).click();

            // Registrant questions are picked first; switching shows only the
            // guest questions' select.
            const conditionForm = modal.locator('form').filter({ has: page.locator('select[name="question_id"]') });
            const registrantSelect = conditionForm.locator('select[data-scope="participant"]');
            const guestSelect = conditionForm.locator('select[data-scope="guest"]');
            await expect(registrantSelect).toBeVisible();
            await expect(guestSelect).toBeHidden();
            await conditionForm.getByLabel('Guest questions').check();
            await expect(guestSelect).toBeVisible();
            await expect(guestSelect).toBeEnabled();
            await expect(registrantSelect).toBeHidden();
            await expect(registrantSelect).toBeDisabled();

            // Each toggle choice is one line with its box beside the text.
            for (const label of await conditionForm.locator('.iccm-checkbox-row').all()) {
                const box = await label.locator('input').boundingBox();
                const row = await label.boundingBox();
                expect(row.height).toBeLessThan(36);
                expect(Math.abs((box.y + box.height / 2) - (row.y + row.height / 2))).toBeLessThan(3);
            }

            await guestSelect.selectOption({ label: 'ui_test_guest_prayer_pals' });
            await conditionForm.locator('select[name="operator"]').selectOption('equals');
            await conditionForm.locator('input[name="value"]').fill('Yes');
            await conditionForm.getByRole('button', { name: 'Add Condition' }).click();

            // The new leaf is tagged as a guest condition, and the refreshed
            // form keeps the guest side picked for the next condition.
            const leaf = modal.locator('.list-group-item', { hasText: 'ui_test_guest_prayer_pals' });
            await expect(leaf).toBeVisible();
            await expect(leaf.locator('.badge', { hasText: 'guest' })).toBeVisible();
            await expect(modal.locator('form').filter({ has: page.locator('select[name="question_id"]') }).getByLabel('Guest questions')).toBeChecked();

            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            expect(overflow).toBeLessThanOrEqual(0);
            await shot(page, testInfo, `report-individual-rule-${name}`);
            await modal.locator('button.close').click();
            await expect(rulesCard.locator('[data-badge="conditional"]')).toBeVisible();

            // Clean up so the list's empty state holds for the other tests.
            await page.goto('/registration/admin/reports');
            page.once('dialog', (dialog) => dialog.accept());
            await page.locator('.iccm-row', { hasText: `UI Individual ${name}` }).getByRole('button', { name: 'Delete', exact: true }).click();
            await expect(page.locator('.alert-success')).toContainText('The report has been deleted.');

            expect(consoleErrors).toEqual([]);
        });
    }

    test('the badge PDF is generated in the browser and downloads as a real PDF', async ({ page, consoleErrors }) => {
        await loginAsAdmin(page);

        // The coordinate-placed Avery grid, with its own generator.
        await exportPdf(page, '/registration/admin/logistics/badges', /^badges-\d{8}-\d{6}\.pdf$/);

        expect(consoleErrors).toEqual([]);
    });

    for (const [path, stem] of [
        ['/registration/admin/rooms/assignments', 'room-assignments'],
        ['/registration/admin/logistics/prayer-pals', 'prayer-pals'],
    ]) {
        test(`the ${stem} PDF takes the orientation and paper options and downloads as a real PDF`, async ({ page, consoleErrors }) => {
            await loginAsAdmin(page);

            await exportPdf(page, path, new RegExp(`^${stem}-\\d{8}-\\d{6}\\.pdf$`), ['Landscape', 'A4']);

            expect(consoleErrors).toEqual([]);
        });
    }

    test('lodging is a top-level nav item and rooms are added by wing and floor', async ({ page, consoleErrors }, testInfo) => {
        await loginAsAdmin(page);

        // Reached from the admin nav directly — not nested under Logistics.
        await page.goto('/registration/admin');
        await page.getByRole('link', { name: 'Lodging' }).click();
        await expect(page).toHaveURL(/\/registration\/admin\/rooms$/);
        await expect(page.locator('h1')).toContainText('Lodging');

        // Add a men-only zone of three rooms in one line, using an inclusive range.
        await page.fill('input[name="wing"]', 'A');
        await page.fill('input[name="floor"]', '1');
        await page.fill('input[name="names"]', '101-103');
        await page.getByRole('button', { name: 'Add', exact: true }).click();

        const zone = page.locator('.card', { hasText: 'Wing A, Floor 1' });
        await expect(zone).toBeVisible();
        await expect(zone).toContainText('0 assigned');
        for (const room of ['101', '102', '103']) {
            await expect(zone.locator(`input[name="name"][value="${room}"]`)).toBeVisible();
        }
        await shot(page, testInfo, 'rooms-zone');

        // Re-designating writes the whole zone.
        await zone.locator('select[name="designation"]').selectOption('couples');
        await zone.getByRole('button', { name: 'Save' }).first().click();
        await expect(
            page.locator('.card', { hasText: 'Wing A, Floor 1' }).locator('select[name="designation"]'),
        ).toHaveValue('couples');

        // The assignments board sees the rooms; with no registrants seeded the
        // first pass reports an empty result rather than failing.
        await page.goto('/registration/admin/rooms/assignments');
        await expect(page.locator('body')).toContainText('Wing A, Floor 1');
        await page.getByRole('button', { name: 'Auto-assign' }).click();
        await expect(page.locator('.alert-success')).toContainText(
            'First pass complete: 0 assigned, 0 still unassigned.',
        );
        await shot(page, testInfo, 'rooms-assignments');

        page.once('dialog', (dialog) => dialog.accept());
        await page.getByRole('button', { name: 'Remove All Assignments' }).click();
        await expect(page.locator('body')).toContainText('Wing A, Floor 1');

        expect(consoleErrors).toEqual([]);
    });
});
