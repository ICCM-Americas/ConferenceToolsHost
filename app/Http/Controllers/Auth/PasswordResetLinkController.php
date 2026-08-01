<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/** Sends the password-reset email. */
class PasswordResetLinkController extends Controller
{
    /** Show the forgot-password form. */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /** Email a reset link to the given address. */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        try {
            $status = Password::sendResetLink($request->only('email'));
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => __('passwords.mail_unavailable')]);
        }

        return $status === Password::ResetLinkSent
            ? back()->with('status', __($status))
            : back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
