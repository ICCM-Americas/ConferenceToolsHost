@extends('layouts.app')

@section('title', __('auth_ui.reset_password'))

@section('content')
    <x-auth-card :title="__('auth_ui.reset_password')">
        <form method="POST" action="{{ route('password.store') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <label for="email">{{ __('auth_ui.email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus>

            @include('auth.partials.password-fields', ['label' => __('auth_ui.new_password')])

            <button type="submit" class="mt-3">{{ __('auth_ui.update_password') }}</button>
        </form>
    </x-auth-card>
@endsection
