<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorService;
use App\Support\LastAdminGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Laravel\Passkeys\Passkey;

/** Admin user management: list, create, inspect, and the account safety actions (lock, admin flag, password resets, passkeys, MFA). */
class UserController extends Controller
{
    public function __construct(private readonly LastAdminGuard $guard) {}

    /** The searchable, filterable user list. */
    public function index(Request $request): View
    {
        $search = $request->string('search')->toString();
        $filter = $request->string('filter')->toString();

        $users = User::query()
            ->withCount('passkeys')
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($filter === 'admins', fn ($q) => $q->where('is_admin', true))
            ->when($filter === 'locked', fn ($q) => $q->where('is_locked', true))
            ->when($filter === 'must_change', fn ($q) => $q->where('must_change_password', true))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
            'filter' => $filter,
        ]);
    }

    /** Show the create-user form. */
    public function create(): View
    {
        return view('admin.users.create');
    }

    /** Create a user with a temporary password they must change at first login. */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            // Length-based rule only (min 12), matching self-registration.
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
            'is_admin' => ['sometimes', 'boolean'],
        ]);

        $user = User::createWithGeneratedName([
            'name' => $validated['name'] ?? null,
            'email' => $validated['email'],
            'password' => $validated['password'],
            'is_admin' => $request->boolean('is_admin'),
            // Admin-set password is temporary: force a change at first login.
            'must_change_password' => true,
        ]);

        return redirect()->route('admin.users.show', $user)->with('status', __('admin.user_created'));
    }

    /** One user's detail page with their passkeys. */
    public function show(User $user): View
    {
        return view('admin.users.show', [
            'user' => $user->loadCount('passkeys'),
            'passkeys' => $user->passkeys()->latest()->get(),
        ]);
    }

    /** Lock the account, unless that would remove the last active admin. */
    public function lock(User $user): RedirectResponse
    {
        if ($this->guard->wouldRemoveLastActiveAdmin($user)) {
            return $this->lastAdminError($user);
        }

        $user->forceFill(['is_locked' => true])->save();

        return $this->back($user, __('admin.user_locked'));
    }

    /** Unlock the account. */
    public function unlock(User $user): RedirectResponse
    {
        $user->forceFill(['is_locked' => false])->save();

        return $this->back($user, __('admin.user_unlocked'));
    }

    /** Grant the admin flag. */
    public function makeAdmin(User $user): RedirectResponse
    {
        $user->forceFill(['is_admin' => true])->save();

        return $this->back($user, __('admin.user_promoted'));
    }

    /** Revoke the admin flag, unless that would remove the last active admin. */
    public function removeAdmin(User $user): RedirectResponse
    {
        if ($this->guard->wouldRemoveLastActiveAdmin($user)) {
            return $this->lastAdminError($user);
        }

        $user->forceFill(['is_admin' => false])->save();

        return $this->back($user, __('admin.user_demoted'));
    }

    /** Email the user a password-reset link. */
    public function triggerPasswordReset(User $user): RedirectResponse
    {
        Password::sendResetLink(['email' => $user->email]);

        return $this->back($user, __('admin.password_reset_sent'));
    }

    /** Set a temporary password the user must change at next login. */
    public function setTemporaryPassword(Request $request, User $user): RedirectResponse
    {
        // Same length-based rules as user-set passwords (min 12, no composition).
        $validated = $request->validate([
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $user->forceFill([
            'password' => $validated['password'],
            // Force the user to choose a new password at next login.
            'must_change_password' => true,
        ])->save();

        return $this->back($user, __('admin.temp_password_set'));
    }

    /** Remove one of the user's passkeys. */
    public function deletePasskey(User $user, Passkey $passkey): RedirectResponse
    {
        abort_unless($passkey->user_id === $user->id, 404);

        $passkey->delete();

        return $this->back($user, __('admin.passkey_removed'));
    }

    /** Clear the user's TOTP enrollment. */
    public function resetMfa(User $user, TwoFactorService $twoFactor): RedirectResponse
    {
        $twoFactor->disable($user);

        return $this->back($user, __('admin.mfa_reset'));
    }

    /** Redirect back to the user's detail page with a status message. */
    private function back(User $user, string $message): RedirectResponse
    {
        return redirect()->route('admin.users.show', $user)->with('status', $message);
    }

    /** The redirect used when an action would remove the last active admin. */
    private function lastAdminError(User $user): RedirectResponse
    {
        return redirect()->route('admin.users.show', $user)
            ->withErrors(['admin' => __('admin.last_admin_protected')]);
    }
}
