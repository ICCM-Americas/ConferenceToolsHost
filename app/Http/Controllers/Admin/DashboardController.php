<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\MigrationStatus;
use ConferenceTools\BoFScheduler\BoFSchedulerServiceProvider;
use ConferenceTools\BoFScheduler\Models\Session;
use ConferenceTools\BoFScheduler\Services\PhaseService;
use ConferenceTools\Registration\Models\Draft;
use ConferenceTools\Registration\Models\Group;
use ConferenceTools\Registration\RegistrationServiceProvider;
use Illuminate\View\View;

/**
 * The host application's own admin dashboard: branding and user management,
 * plus at-a-glance summaries that link through to the registration and
 * BoF-scheduler package dashboards. The summary figures are sourced from the
 * packages themselves (Group / Session / PhaseService) so the definition of a
 * "registration" or a "nominated topic" lives in one place.
 */
class DashboardController extends Controller
{
    /** The admin dashboard: user counts, pending-migration prompt, and the installed packages' summaries. */
    public function index(): View
    {
        $data = [
            'userCount' => User::count(),
            'adminCount' => User::where('is_admin', true)->count(),
            // Prompt to apply outstanding migrations (e.g. right after a deploy).
            // Zero when the database is up to date, which hides the prompt.
            'pendingMigrations' => MigrationStatus::pendingCount(),
        ];

        // The registration and BoF summaries are sourced from the optional
        // packages; gather each only when its package is installed (the dashboard
        // view hides the matching card otherwise). Resolving the figures lazily —
        // and not type-hinting PhaseService in the signature — keeps the host
        // dashboard rendering even when a package is absent.
        if (AppServiceProvider::packageInstalled(RegistrationServiceProvider::class)) {
            // Registration summary (mirrors the registration admin dashboard).
            $data['registrationsComplete'] = Group::registeredParticipantCount();
            $data['registrationsIncomplete'] = Draft::incompleteCount();
        }

        if (AppServiceProvider::packageInstalled(BoFSchedulerServiceProvider::class)) {
            // BoF summary (mirrors the scheduler admin dashboard).
            $phase = app(PhaseService::class);
            $data['currentPhase'] = $phase->current();
            $data['nominatedCount'] = Session::nominated()->count();
        }

        return view('admin.dashboard', $data);
    }
}
