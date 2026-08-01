@extends('layouts.app')

@section('title', $user->name)

@section('content')
    <p><a href="{{ route('admin.users.index') }}">← {{ __('admin.back_to_users') }}</a></p>
    <h1>{{ $user->name }}</h1>
    <p class="iccm-muted">{{ $user->email }}</p>

    <div class="iccm-card">
        <p>
            <span class="iccm-pill">{{ $user->is_admin ? __('admin.role_admin') : __('admin.role_user') }}</span>
            <span class="iccm-pill">{{ $user->is_locked ? __('admin.status_locked') : __('admin.status_active') }}</span>
            <span class="iccm-pill">{{ __('admin.passkeys') }}: {{ $user->passkeys_count }}</span>
            <span class="iccm-pill">{{ __('admin.mfa') }}: {{ $user->hasTwoFactorEnabled() ? __('admin.mfa_on') : __('admin.mfa_off') }}</span>
            @if ($user->must_change_password)<span class="iccm-pill">{{ __('admin.must_change_password') }}</span>@endif
        </p>
    </div>

    <div class="iccm-card">
        <h2>{{ __('admin.account_actions') }}</h2>
        <div class="iccm-row iccm-row-tight">
            @if ($user->is_locked)
                <form method="POST" action="{{ route('admin.users.unlock', $user) }}">@csrf<button type="submit">{{ __('admin.unlock') }}</button></form>
            @else
                <form method="POST" action="{{ route('admin.users.lock', $user) }}">@csrf<button type="submit" class="iccm-danger">{{ __('admin.lock') }}</button></form>
            @endif

            @if ($user->is_admin)
                <form method="POST" action="{{ route('admin.users.remove-admin', $user) }}">@csrf<button type="submit" class="iccm-ghost">{{ __('admin.remove_admin') }}</button></form>
            @else
                <form method="POST" action="{{ route('admin.users.make-admin', $user) }}">@csrf<button type="submit" class="iccm-ghost">{{ __('admin.make_admin') }}</button></form>
            @endif

            <form method="POST" action="{{ route('admin.users.trigger-password-reset', $user) }}">@csrf<button type="submit" class="iccm-ghost">{{ __('admin.send_password_reset') }}</button></form>
            <form method="POST" action="{{ route('admin.users.reset-mfa', $user) }}">@csrf<button type="submit" class="iccm-ghost">{{ __('admin.reset_mfa') }}</button></form>
        </div>
    </div>

    <div class="iccm-card">
        <h2>{{ __('admin.set_temp_password') }}</h2>
        <p class="iccm-muted">{{ __('admin.set_temp_password_intro') }}</p>
        <form method="POST" action="{{ route('admin.users.set-temporary-password', $user) }}">
            @csrf
            <label>{{ __('admin.temp_password') }}</label>
            <input type="password" name="password" required class="temp-password-input">
            <label>{{ __('admin.confirm') }}</label>
            <input type="password" name="password_confirmation" required class="temp-password-input">
            <button type="submit" class="mt-2">{{ __('admin.set_temp_password') }}</button>
        </form>
    </div>

    <div class="iccm-card">
        <h2>{{ __('admin.passkeys') }}</h2>
        @forelse ($passkeys as $passkey)
            <div class="iccm-checkbox-row iccm-list-row iccm-list-row-tight iccm-list-row-split">
                <span>{{ $passkey->name }}</span>
                <form method="POST" action="{{ route('admin.users.passkeys.destroy', [$user, $passkey]) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="iccm-danger">{{ __('admin.remove') }}</button>
                </form>
            </div>
        @empty
            <p class="iccm-muted">{{ __('admin.no_passkeys') }}</p>
        @endforelse
    </div>
@endsection
