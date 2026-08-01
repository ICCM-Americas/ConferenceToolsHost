<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Users flagged must_change_password are forced to the password-change screen
 * before they can reach the rest of the application.
 */
class EnsurePasswordIsCurrent
{
    /** Divert users flagged for a forced password change to that flow first. */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            // Allow the change-password screen, its submission, and logout.
            $allowed = [
                'password.forced.edit',
                'password.forced.update',
                'logout',
            ];

            if (! in_array($request->route()?->getName(), $allowed, true)) {
                return redirect()->route('password.forced.edit');
            }
        }

        return $next($request);
    }
}
