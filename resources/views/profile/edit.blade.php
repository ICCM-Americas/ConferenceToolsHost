@extends('layouts.app')

@section('title', __('profile.heading'))

@section('content')
    <h1>{{ __('profile.heading') }}</h1>

    <div class="iccm-card">
        <h2>{{ __('profile.display_name') }}</h2>
        <form method="POST" action="{{ route('profile.update') }}">
            @csrf
            @method('PATCH')
            <label for="name">{{ __('profile.display_name') }}</label>
            <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required>
            <label for="email">{{ __('profile.email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required>
            <button type="submit" class="form-submit-btn">{{ __('profile.save') }}</button>
        </form>
    </div>

    <div class="iccm-card">
        <h2>{{ __('profile.change_password') }}</h2>
        <form method="POST" action="{{ route('profile.password') }}">
            @csrf
            @method('PUT')
            <label for="current_password">{{ __('profile.current_password') }}</label>
            <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
            <label for="password">{{ __('profile.new_password') }}</label>
            <input id="password" type="password" name="password" required autocomplete="new-password">
            <p class="iccm-muted">{{ __('auth_ui.password_hint') }}</p>
            <label for="password_confirmation">{{ __('profile.confirm_password') }}</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            <button type="submit" class="form-submit-btn">{{ __('profile.change_password') }}</button>
        </form>
    </div>

    <div class="iccm-card">
        <h2>{{ __('profile.passkeys') }}</h2>
        <p class="iccm-muted">{{ __('profile.passkeys_intro') }}</p>
        @forelse ($passkeys as $passkey)
            <div class="iccm-checkbox-row iccm-list-row iccm-list-row-tight iccm-list-row-split">
                <span>{{ $passkey->name }} <span class="iccm-muted">({{ $passkey->created_at->diffForHumans() }})</span></span>
                <form method="POST" action="{{ url('user/passkeys/'.$passkey->id) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="iccm-danger">✕</button>
                </form>
            </div>
        @empty
            <p class="iccm-muted">{{ __('profile.no_passkeys') }}</p>
        @endforelse

        <div class="add-passkey-row">
            <input id="passkey_name" type="text" placeholder="{{ __('profile.add_passkey') }}" class="passkey-name-input">
            <button type="button" id="passkey-register-btn" class="iccm-secondary"
                data-options-url="{{ route('passkey.registration-options') }}"
                data-store-url="{{ route('passkey.store') }}"
                data-redirect-url="{{ route('profile.edit') }}">
                {{ __('profile.add_passkey') }}
            </button>
        </div>
    </div>

    <div class="iccm-card">
        <h2>{{ __('profile.mfa') }}</h2>
        <p>
            @if ($twoFactorEnabled)
                <span class="iccm-pill">{{ __('profile.mfa_on') }}</span>
            @else
                <span class="iccm-pill">{{ __('profile.mfa_off') }}</span>
            @endif
            <a href="{{ route('two-factor.show') }}" class="iccm-btn iccm-ghost">{{ __('profile.mfa') }}</a>
        </p>
    </div>

    <div class="iccm-card">
        <h2>{{ __('profile.delete_account') }}</h2>
        <p class="iccm-muted">{{ __('profile.delete_warning') }}</p>
        <form method="POST" action="{{ route('profile.destroy') }}" id="delete-account-form">
            @csrf
            @method('DELETE')
            <label for="password_delete">{{ __('profile.delete_confirm_password') }}</label>
            <input id="password_delete" type="password" name="password" required class="delete-password-input">
            <button type="submit" class="iccm-danger form-submit-btn">{{ __('profile.delete_account') }}</button>
        </form>
    </div>

    @include('partials.passkey-scripts')

    <script nonce="{{ $cspNonce ?? '' }}">
    document.addEventListener('DOMContentLoaded', function () {
        const deleteForm = document.getElementById('delete-account-form');
        if (deleteForm) {
            deleteForm.addEventListener('submit', function (event) {
                if (!confirm(@json(__('profile.delete_account') . '?'))) {
                    event.preventDefault();
                }
            });
        }
    });
    </script>
@endsection
