@extends('layouts.app')

@section('title', __('auth_ui.login'))

@section('content')
    <x-auth-card :title="__('auth_ui.login')">
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <label for="email">{{ __('auth_ui.email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">

            <label for="password">{{ __('auth_ui.password') }}</label>
            <input id="password" type="password" name="password" required autocomplete="current-password">

            <div class="iccm-checkbox-row remember-row">
                <input id="remember" type="checkbox" name="remember" value="1">
                <label for="remember" class="m-0">{{ __('auth_ui.remember') }}</label>
            </div>

            <button type="submit" class="mt-3">{{ __('auth_ui.login') }}</button>
        </form>

        <hr class="auth-divider">

        <button type="button" id="passkey-login-btn" class="iccm-secondary"
            data-options-url="{{ route('passkey.login-options') }}"
            data-login-url="{{ route('passkey.login') }}"
            data-redirect-url="{{ route('home') }}">
            {{ __('auth_ui.login_passkey') }}
        </button>

        <p class="mt-3">
            <a href="{{ route('password.request') }}">{{ __('auth_ui.forgot') }}</a>
            &middot;
            <a href="{{ route('register') }}">{{ __('auth_ui.need_account') }}</a>
        </p>
    </x-auth-card>

    @include('partials.passkey-scripts')
@endsection
