<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PhaseRedirector;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Self-service account registration. */
class RegisteredUserController extends Controller
{
    /** Show the registration form. */
    public function create(): View
    {
        return view('auth.register');
    }

    /** Create the account and sign it in. */
    public function store(Request $request, PhaseRedirector $redirector): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            // Length-based rule only (min 12), no composition requirements.
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::createWithGeneratedName([
            'name' => $validated['name'] ?? null,
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route($redirector->routeFor($user));
    }
}
