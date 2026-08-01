<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Admits only signed-in admin accounts; everyone else gets 403. */
class EnsureUserIsAdmin
{
    /** Reject the request unless the user is an admin. */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            abort(403);
        }

        return $next($request);
    }
}
