@extends('layouts.app')

@section('title', __('auth_ui.reset_password'))

@section('content')
    <div class="iccm-card iccm-narrow-card">
        <h1>{{ __('auth_ui.reset_password') }}</h1>

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <label for="email">{{ __('auth_ui.email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>

            <button type="submit" class="mt-3">{{ __('auth_ui.send_reset_link') }}</button>
        </form>
    </div>
@endsection
