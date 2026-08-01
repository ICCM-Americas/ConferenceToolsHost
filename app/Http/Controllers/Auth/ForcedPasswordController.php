<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\PhaseRedirector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Handles the "you must change your password" flow triggered when an admin sets
 * a temporary password (must_change_password = true).
 */
class ForcedPasswordController extends Controller
{
    /** Show the mandatory password-change form. */
    public function edit(): View
    {
        return view('auth.forced-password');
    }

    /** Set the new password and clear the forced-change flag. */
    public function update(Request $request, PhaseRedirector $redirector): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();
        $user->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        return redirect()->route($redirector->routeFor($user->fresh()))
            ->with('status', __('auth.password_updated'));
    }
}
