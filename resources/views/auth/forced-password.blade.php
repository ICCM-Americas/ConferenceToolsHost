@extends('layouts.app')

@section('title', __('auth_ui.forced_title'))

@section('content')
    <x-auth-card :title="__('auth_ui.forced_title')">
        <p class="iccm-muted">{{ __('auth_ui.forced_intro') }}</p>

        <form method="POST" action="{{ route('password.forced.update') }}">
            @csrf
            @method('PUT')

            @include('auth.partials.password-fields', ['label' => __('auth_ui.new_password'), 'autofocus' => true])

            <button type="submit" class="mt-3">{{ __('auth_ui.update_password') }}</button>
        </form>
    </x-auth-card>
@endsection
