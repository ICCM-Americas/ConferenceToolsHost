<?php

namespace App\Http\Controllers;

use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Self-service MFA management for the signed-in user: enroll, confirm, view
 * recovery codes, and disable.
 */
class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    /** The MFA management page, including any pending enrollment. */
    public function show(Request $request): View
    {
        $user = $request->user();
        $qrUrl = $request->session()->get('two_factor_pending_qr');

        return view('profile.two-factor', [
            'enabled' => $user->hasTwoFactorEnabled(),
            'pendingSecret' => $request->session()->get('two_factor_pending_secret'),
            'qrImage' => $qrUrl ? $this->twoFactor->qrCodeImage($qrUrl) : null,
            'recoveryCodes' => $user->hasTwoFactorEnabled() ? $this->twoFactor->recoveryCodes($user) : [],
        ]);
    }

    /** Start enrollment: generate a secret and QR code to confirm. */
    public function enable(Request $request): RedirectResponse
    {
        $user = $request->user();
        $secret = $this->twoFactor->enable($user);

        // Stash the pending secret/QR so the confirm step can render them.
        $request->session()->put('two_factor_pending_secret', $secret);
        $request->session()->put('two_factor_pending_qr', $this->twoFactor->qrCodeUrl($user, $secret));

        return redirect()->route('two-factor.show');
    }

    /** Complete enrollment by verifying a code from the authenticator. */
    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        if (! $this->twoFactor->confirm($request->user(), $request->string('code'))) {
            return back()->withErrors(['code' => __('auth.two_factor_invalid')]);
        }

        $request->session()->forget(['two_factor_pending_secret', 'two_factor_pending_qr']);

        return redirect()->route('two-factor.show')->with('status', __('profile.two_factor_enabled'));
    }

    /** Disable MFA for the signed-in user. */
    public function destroy(Request $request): RedirectResponse
    {
        $this->twoFactor->disable($request->user());

        return redirect()->route('two-factor.show')->with('status', __('profile.two_factor_disabled'));
    }
}
