@extends('layouts.app')

@section('title', __('auth_ui.register'))

@section('content')
    <x-auth-card :title="__('auth_ui.register')">
        <form method="POST" action="{{ route('register') }}">
            @csrf
            <label for="name">{{ __('auth_ui.name_optional') }}</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" autofocus>
            <p class="iccm-muted">{{ __('auth_ui.name_optional_hint') }}</p>

            <label for="email">{{ __('auth_ui.email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">

            @include('auth.partials.password-fields')

            <button type="submit" class="mt-3">{{ __('auth_ui.register') }}</button>
        </form>

        <p class="mt-3">
            <a href="{{ route('login') }}">{{ __('auth_ui.have_account') }}</a>
        </p>
    </x-auth-card>
@endsection
