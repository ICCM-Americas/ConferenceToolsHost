@extends('layouts.app')

@section('title', __('profile.mfa'))

@section('content')
    <h1>{{ __('profile.mfa') }}</h1>

    @if ($enabled)
        <div class="iccm-card">
            <p><span class="iccm-pill">{{ __('profile.mfa_on') }}</span></p>

            @if (! empty($recoveryCodes))
                <h2>{{ __('profile.recovery_codes') }}</h2>
                <p class="iccm-muted">{{ __('profile.recovery_intro') }}</p>
                <pre class="recovery-codes">@foreach ($recoveryCodes as $code){{ $code }}
@endforeach</pre>
            @endif

            <form method="POST" action="{{ route('two-factor.destroy') }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="iccm-danger">{{ __('profile.disable_mfa') }}</button>
            </form>
        </div>
    @elseif ($pendingSecret)
        <div class="iccm-card">
            <p class="iccm-muted">{{ __('profile.mfa_scan') }}</p>
            <img src="{{ $qrImage }}" alt="{{ __('profile.qr_code_alt') }}" width="200" height="200">
            <p class="iccm-muted">{{ __('profile.mfa_secret') }}: <code>{{ $pendingSecret }}</code></p>

            <form method="POST" action="{{ route('two-factor.confirm') }}">
                @csrf
                <label for="code">{{ __('profile.confirm_code') }}</label>
                <input id="code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required class="code-input">
                <button type="submit" class="confirm-btn">{{ __('profile.confirm') }}</button>
            </form>
        </div>
    @else
        <div class="iccm-card">
            <form method="POST" action="{{ route('two-factor.enable') }}">
                @csrf
                <button type="submit">{{ __('profile.enable_mfa') }}</button>
            </form>
        </div>
    @endif

    <p><a href="{{ route('profile.edit') }}">{{ __('profile.back_to_profile') }}</a></p>
@endsection
