@extends('layouts.app')

@section('title', __('auth_ui.two_factor_title'))

@section('content')
    <div class="iccm-card iccm-narrow-card">
        <h1>{{ __('auth_ui.two_factor_title') }}</h1>
        <p class="iccm-muted">{{ __('auth_ui.two_factor_prompt') }}</p>

        <form method="POST" action="{{ route('two-factor.challenge') }}">
            @csrf
            <label for="code">{{ __('auth_ui.code') }}</label>
            <input id="code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus>

            <details class="recovery-details">
                <summary>{{ __('auth_ui.use_recovery') }}</summary>
                <label for="recovery_code">{{ __('auth_ui.recovery_code') }}</label>
                <input id="recovery_code" type="text" name="recovery_code" autocomplete="off">
            </details>

            <button type="submit" class="mt-3">{{ __('auth_ui.verify') }}</button>
        </form>
    </div>
@endsection
