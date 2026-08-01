<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorService;
use App\Support\PhaseRedirector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Second factor at login. The user has already proven their password; here they
 * supply a TOTP code or a recovery code before the session is authenticated.
 */
class TwoFactorChallengeController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    /** Show the second-factor prompt for the pending login. */
    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    /** Verify the TOTP or recovery code and complete the login. */
    public function store(Request $request, PhaseRedirector $redirector): RedirectResponse
    {
        $userId = $request->session()->get('login.id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::findOrFail($userId);

        $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $passed = false;

        if ($request->filled('code')) {
            $passed = $this->twoFactor->verify($user, $request->string('code'));
        } elseif ($request->filled('recovery_code')) {
            $passed = $this->twoFactor->useRecoveryCode($user, $request->string('recovery_code'));
        }

        if (! $passed) {
            throw ValidationException::withMessages([
                'code' => __('auth.two_factor_invalid'),
            ]);
        }

        $remember = (bool) $request->session()->pull('login.remember', false);
        $request->session()->forget('login.id');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->route($redirector->routeFor($user));
    }
}
