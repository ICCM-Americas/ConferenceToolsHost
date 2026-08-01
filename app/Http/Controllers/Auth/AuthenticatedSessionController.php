<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PhaseRedirector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Password login and logout. */
class AuthenticatedSessionController extends Controller
{
    /** Show the login form. */
    public function create(): View
    {
        return view('auth.login');
    }

    /** Authenticate the credentials, honoring account locks and the optional second factor. */
    public function store(Request $request, PhaseRedirector $redirector): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // Locked users may never log in.
        if ($user->is_locked) {
            throw ValidationException::withMessages([
                'email' => __('auth.locked'),
            ]);
        }

        // If MFA is enabled, defer login until the challenge is satisfied.
        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('login.id', $user->id);
            $request->session()->put('login.remember', $request->boolean('remember'));

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route($redirector->routeFor($user)));
    }

    /** Log out and invalidate the session. */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('scheduler.results.public');
    }
}
