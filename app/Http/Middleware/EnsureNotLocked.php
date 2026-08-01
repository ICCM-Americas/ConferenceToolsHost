<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defense in depth: if an authenticated user is locked mid-session, log them
 * out immediately. (Locked users are also blocked at the login boundary.)
 */
class EnsureNotLocked
{
    /** Block the request when the account has been locked. */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->is_locked) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => __('auth.locked'),
            ]);
        }

        return $next($request);
    }
}
