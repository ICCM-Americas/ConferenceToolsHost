<?php

namespace App\Support;

use App\Models\User;
use ConferenceTools\BoFScheduler\Support\BoFSchedulerLandingPage;

/**
 * Decides where a user lands after authentication, based on role and the forced
 * password-change flag. Non-admins are forwarded to the scheduler package's
 * phase-appropriate landing page; the package owns that phase logic, the host
 * owns *when* to redirect (here, right after login).
 */
class PhaseRedirector
{
    public function __construct(private readonly BoFSchedulerLandingPage $landing) {}

    /** The route name this user should land on after authenticating. */
    public function routeFor(User $user): string
    {
        if ($user->must_change_password) {
            return 'password.forced.edit';
        }

        if ($user->isAdmin()) {
            return 'scheduler.admin.dashboard';
        }

        // Non-admins go straight to the phase-appropriate scheduler page.
        return $this->landing->routeFor($user);
    }
}
