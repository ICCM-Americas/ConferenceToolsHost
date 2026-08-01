<?php

namespace Database\Seeders;

use App\Models\User;
use ConferenceTools\BoFScheduler\Database\Seeders\BoFSchedulerSeeder;
use ConferenceTools\BoFScheduler\Enums\EventPhase;
use ConferenceTools\BoFScheduler\Enums\SessionStatus;
use ConferenceTools\BoFScheduler\Models\PhaseSetting;
use ConferenceTools\BoFScheduler\Models\Session;
use ConferenceTools\BoFScheduler\Models\SessionPreference;
use ConferenceTools\BoFScheduler\Services\PhaseService;
use ConferenceTools\Registration\Database\Seeders\ClosedMessageSeeder;
use ConferenceTools\Registration\Database\Seeders\InfoStepSeeder;
use ConferenceTools\Registration\Database\Seeders\SystemQuestionsSeeder;
use ConferenceTools\Registration\Enums\QuestionScope;
use ConferenceTools\Registration\Enums\QuestionType;
use ConferenceTools\Registration\Models\BaseCharge;
use ConferenceTools\Registration\Models\Currency;
use ConferenceTools\Registration\Models\Question;
use ConferenceTools\Registration\Models\Section;
use ConferenceTools\Registration\Services\GuestQuestions;
use ConferenceTools\Registration\Services\ReportQuestions;
use Illuminate\Database\Seeder;

/**
 * Deterministic fixtures for the black-box Playwright UI suite (tests/ui).
 *
 * This is NOT used by the application or the PHPUnit suite — it is invoked only
 * by the Playwright global setup against an isolated sqlite database
 * (database/ui-testing.sqlite). It builds the suite's two fixed accounts, the
 * package baseline data, and just enough scheduler data for the interactive
 * screens (selection, session editing) to render their JavaScript-driven
 * controls. The accounts have known passwords and exist only here — nothing
 * the application or a deploy runs can create them.
 */
class UiTestingSeeder extends Seeder
{
    public function run(): void
    {
        // Host accounts. These live here, not in a general-purpose seeder, so
        // that no deployable code path can ever create an account with a known
        // password — a real deployment promotes its first admin by hand (see
        // the README).
        $admin = User::firstOrNew(['email' => 'admin@example.com']);
        $admin->forceFill([
            'name' => 'Administrator',
            'password' => 'password-please-change',
            'is_admin' => true,
        ])->save();

        $demo = User::updateOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Demo User',
                'password' => 'password-please-change',
            ],
        );

        // Scheduler baseline data (phase settings, example rooms/time slots) is
        // owned by the package — it never touches users/auth.
        $this->call(BoFSchedulerSeeder::class);

        // Registration baseline data: the default landing-page steps and
        // closed-page messages (admins edit them afterwards on the
        // registration admin "Landing Page" and "Closed Page" consoles).
        $this->call(InfoStepSeeder::class);
        $this->call(ClosedMessageSeeder::class);

        // A nominated BoF owned by the demo user. Gives the selection screen a
        // session row (interest radios + "willing to facilitate") and the admin
        // session-edit screen something to attach facilitators to.
        $session = Session::firstOrCreate(
            ['title' => 'UI Test BoF'],
            [
                'description' => 'A session created for the UI test suite.',
                'is_permanent' => false,
                'is_selected' => false,
                'status' => SessionStatus::Active->value,
                'submitted_by_user_id' => $demo->id,
                'submitter_display_name' => $demo->name,
            ],
        );

        // An interested preference so the demo user shows up as a facilitator
        // candidate in the admin session-edit "add facilitator" dropdown.
        SessionPreference::firstOrCreate(
            ['session_id' => $session->id, 'user_id' => $demo->id],
            ['would_attend' => true, 'willing_to_facilitate' => true],
        );

        // A registration section with one wordy question, so the form-builder
        // screens render real rows (the narrow-viewport responsive spec needs
        // content long enough to have to wrap).
        $section = Section::firstOrCreate(
            ['scope' => QuestionScope::Participant->value, 'key' => 'ui-test-details'],
            ['title' => 'UI Test Details', 'position' => 0, 'enabled' => true],
        );
        Question::firstOrCreate(
            ['section_id' => $section->id, 'key' => 'ui_test_badge_name'],
            [
                'type' => QuestionType::Text->value,
                'label' => 'Your full name as it should appear on your printed conference badge',
                'required' => true,
                'position' => 0,
                'enabled' => true,
            ],
        );

        // A choice question with options, so the per-option visibility editor
        // on the question edit form has rows to open the shared modal from.
        // Optional, so the wizard test-drive spec can pass the step without
        // answering it.
        $pass = Question::firstOrCreate(
            ['section_id' => $section->id, 'key' => 'ui_test_pass'],
            [
                'type' => QuestionType::Radio->value,
                'label' => 'Pass Type',
                'required' => false,
                'position' => 1,
                'enabled' => true,
            ],
        );
        $pass->options()->firstOrCreate(['value' => 'full'], ['label' => 'Full Pass', 'position' => 0]);
        $pass->options()->firstOrCreate(['value' => 'day'], ['label' => 'Day Pass', 'position' => 1]);

        // A dedicated scratch fixture for question-options.spec.js: that spec
        // permanently renames, adds, and deletes options as it exercises the
        // Options-list editor, so it must not share ui_test_pass with
        // registration-reports.spec.js, which depends on ui_test_pass's
        // options staying exactly as seeded.
        $optionsEditor = Question::firstOrCreate(
            ['section_id' => $section->id, 'key' => 'ui_test_options_editor'],
            [
                'type' => QuestionType::Radio->value,
                'label' => 'Options Editor Test',
                'required' => false,
                'position' => 2,
                'enabled' => true,
            ],
        );
        $optionsEditor->options()->firstOrCreate(['value' => 'full'], ['label' => 'Full Pass', 'position' => 0]);
        $optionsEditor->options()->firstOrCreate(['value' => 'day'], ['label' => 'Day Pass', 'position' => 1]);

        // The two protected system questions (Question::GUEST_TRIGGER_KEY,
        // Question::GROUP_TRIGGER_KEY) are migrated in already — live,
        // together in their own dedicated section (see SystemQuestionsSeeder).
        // Move them into "UI Test Details" instead, then disable their
        // now-empty original section so it doesn't add a spurious extra step.
        // Each is a reactive trigger question (see RegistrationWizard::expandSection()),
        // so it renders as its own step, in this position order: the badge
        // name/pass/options-editor batch, then "are you bringing a guest?",
        // then "are you registering a group?".
        $hasGuest = Question::where('key', Question::GUEST_TRIGGER_KEY)->firstOrFail();
        $hasGuest->update(['section_id' => $section->id, 'position' => 3]);
        Question::where('key', Question::GROUP_TRIGGER_KEY)->firstOrFail()
            ->update(['section_id' => $section->id, 'position' => 4]);
        Section::where('key', SystemQuestionsSeeder::SECTION_KEY)->update(['enabled' => false]);

        // The guest's own (Guest-scope) question set: a name, enough for the
        // guest-registration UI spec to add/edit/remove a guest, plus a Radio
        // question so the logistics page's Prayer Pals nomination has a
        // Guest-scope Radio question to demonstrate the matching-answer
        // droplist widget against (registration-reports.spec.js).
        $guestSection = Section::firstOrCreate(
            ['scope' => QuestionScope::Guest->value, 'key' => 'ui-test-guest-details'],
            ['title' => 'UI Test Guest Details', 'position' => 0, 'enabled' => true],
        );
        Question::firstOrCreate(
            ['section_id' => $guestSection->id, 'key' => 'ui_test_guest_name'],
            [
                'type' => QuestionType::Text->value,
                'label' => "Guest's full name",
                'required' => true,
                'position' => 0,
                'enabled' => true,
            ],
        );
        $guestPrayerPals = Question::firstOrCreate(
            ['section_id' => $guestSection->id, 'key' => 'ui_test_guest_prayer_pals'],
            [
                'type' => QuestionType::Radio->value,
                'label' => 'Include in Prayer Pals?',
                'required' => false,
                'position' => 1,
                'enabled' => true,
            ],
        );
        $guestPrayerPals->options()->firstOrCreate(['value' => 'Yes'], ['label' => 'Yes', 'position' => 0]);
        $guestPrayerPals->options()->firstOrCreate(['value' => 'No'], ['label' => 'No', 'position' => 1]);

        app(GuestQuestions::class)->update([
            'guest_name_key' => 'ui_test_guest_name',
        ]);

        // The badge-name, first-name, and last-name questions are required
        // nominations before registration may open at all (see
        // RegistrationStatus::requiredNameQuestionsConfigured()) — nominated
        // here, all onto the one seeded name question, so the window spec
        // hits the (expected) email guard rather than this one.
        app(ReportQuestions::class)->update([
            ReportQuestions::BADGE_NAME_KEY => 'ui_test_badge_name',
            ReportQuestions::FIRSTNAME_KEY => 'ui_test_badge_name',
            ReportQuestions::LASTNAME_KEY => 'ui_test_badge_name',
        ]);

        // A default currency and a flat conference charge, so the wizard's
        // final step renders the cost summary card for the test-drive spec.
        Currency::firstOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'rate' => 1, 'def' => true, 'enabled' => true],
        );
        BaseCharge::firstOrCreate(
            ['name' => 'UI Test Conference Fee'],
            ['amount' => 100, 'enabled' => true, 'order' => 0],
        );

        // Pin the event to the Selection phase so the selection screen is live
        // for regular users while admins retain access to every admin screen.
        // Mirrors Tests\TestCase::setPhase().
        PhaseSetting::singleton()->update([
            'automatic_phase_changes_enabled' => false,
            'manual_phase_override' => EventPhase::Selection->value,
        ]);

        app(PhaseService::class)->flush();
    }
}
