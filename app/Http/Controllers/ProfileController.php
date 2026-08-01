<?php

namespace App\Http\Controllers;

use App\Services\TwoFactorService;
use App\Support\LastAdminGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Self-service profile: contact details, password, passkeys overview, and account deletion. */
class ProfileController extends Controller
{
    /** The signed-in user's profile page. */
    public function edit(Request $request, TwoFactorService $twoFactor): View
    {
        $user = $request->user();

        return view('profile.edit', [
            'user' => $user,
            'passkeys' => $user->passkeys()->latest()->get(),
            'twoFactorEnabled' => $user->hasTwoFactorEnabled(),
            'recoveryCodes' => $user->hasTwoFactorEnabled() ? $twoFactor->recoveryCodes($user) : [],
        ]);
    }

    /** Save the name and email address. */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,'.$request->user()->id],
        ]);

        $request->user()->fill($validated)->save();

        return redirect()->route('profile.edit')->with('status', __('profile.updated'));
    }

    /** Change the password after confirming the current one. */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        return redirect()->route('profile.edit')->with('status', __('profile.password_updated'));
    }

    /** Delete the account after password confirmation. */
    public function destroy(Request $request, LastAdminGuard $guard): RedirectResponse
    {
        $user = $request->user();

        // Never let the last active admin delete themselves out of access.
        if ($guard->wouldRemoveLastActiveAdmin($user)) {
            return redirect()->route('profile.edit')->withErrors([
                'delete' => __('admin.last_admin_protected'),
            ]);
        }

        $request->validate(['password' => ['required', 'current_password']]);

        Auth::logout();

        // Nominations are preserved: the scheduler package listens for user
        // deletion and nulls the submitter id while keeping the historical
        // submitter display name. (See BoFSchedulerServiceProvider.)
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('scheduler.results.public')->with('status', __('profile.deleted'));
    }
}
