{{-- New-password and confirmation inputs with a strength hint, shared by
     every form that sets a password. Optional overrides: label, hint,
     autofocus. --}}
@php
    $label ??= __('auth_ui.password');
    $hint ??= __('auth_ui.password_hint');
    $autofocus ??= false;
@endphp
<label for="password">{{ $label }}</label>
<input id="password" type="password" name="password" required @if ($autofocus) autofocus @endif autocomplete="new-password">
<p class="iccm-muted">{{ $hint }}</p>

<label for="password_confirmation">{{ __('auth_ui.confirm_password') }}</label>
<input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
