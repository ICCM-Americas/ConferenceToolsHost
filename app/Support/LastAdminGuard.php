<?php

namespace App\Support;

use App\Models\User;

/**
 * Central safeguard preventing the last *active* admin from being disabled in
 * any way (demote, lock, delete). An active admin is an admin that is not
 * locked — i.e. one who can actually sign in and administer the system.
 */
class LastAdminGuard
{
    /** How many unlocked admins can currently sign in. */
    public function activeAdminCount(): int
    {
        return User::query()
            ->where('is_admin', true)
            ->where('is_locked', false)
            ->count();
    }

    /**
     * Would removing this user as an active admin leave zero active admins?
     * True means the action must be blocked.
     */
    public function wouldRemoveLastActiveAdmin(User $user): bool
    {
        if (! $user->isActiveAdmin()) {
            return false;
        }

        return $this->activeAdminCount() <= 1;
    }
}
