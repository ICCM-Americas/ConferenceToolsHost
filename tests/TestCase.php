<?php

namespace Tests;

use App\Models\User;
use ConferenceTools\BoFScheduler\Enums\EventPhase;
use ConferenceTools\BoFScheduler\Models\PhaseSetting;
use ConferenceTools\BoFScheduler\Services\PhaseService;
use ConferenceTools\Registration\Models\Setting;
use ConferenceTools\Registration\Services\RegistrationEmails;
use ConferenceTools\Registration\Services\RegistrationStatus;
use ConferenceTools\Registration\Services\ReportQuestions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Force the effective phase via a manual override (admin-only concept), and
     * refresh the cached PhaseService so the change is visible immediately.
     */
    protected function setPhase(EventPhase $phase): void
    {
        $settings = PhaseSetting::singleton();
        $settings->update([
            'automatic_phase_changes_enabled' => false,
            'manual_phase_override' => $phase->value,
        ]);

        app(PhaseService::class)->flush();
    }

    /**
     * Open the registration package's window (with the required admin address
     * and the required badge/first/last-name question nominations configured —
     * none of the three has a fallback, see RegistrationStatus::isOpen()), so
     * registrant-facing controls — e.g. the home booking card — are rendered.
     * Registration defaults to closed.
     */
    protected function openRegistration(): void
    {
        app(RegistrationEmails::class)->update('admin@example.com', null);
        Setting::put(ReportQuestions::BADGE_NAME_KEY, 'badgename');
        Setting::put(ReportQuestions::FIRSTNAME_KEY, 'firstname');
        Setting::put(ReportQuestions::LASTNAME_KEY, 'lastname');
        app(RegistrationStatus::class)->schedule(now()->subMinute(), null);
    }

    /** An admin user. */
    protected function createAdmin(array $attributes = []): User
    {
        return User::factory()->admin()->create($attributes);
    }

    /** A regular (non-admin) user. */
    protected function createUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }
}
