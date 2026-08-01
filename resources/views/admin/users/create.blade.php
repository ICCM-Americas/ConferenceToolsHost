@extends('layouts.app')

@section('title', __('admin.create_user'))

@section('content')
    @if ($bof_schedulerInstalled)
        @include('bofscheduler::partials.admin-nav')
    @endif
    <p><a href="{{ route('admin.users.index') }}">← {{ __('admin.back_to_users') }}</a></p>
    <h1>{{ __('admin.create_user') }}</h1>

    <div class="iccm-card">
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf

            <label for="name">{{ __('auth_ui.name_optional') }}</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" autofocus>
            <p class="iccm-muted">{{ __('auth_ui.name_optional_hint') }}</p>

            <label for="email">{{ __('auth_ui.email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required>

            @include('auth.partials.password-fields', ['hint' => __('admin.create_user_password_hint')])

            <label class="iccm-checkbox-row is-admin-row">
                <input type="checkbox" name="is_admin" value="1" @checked(old('is_admin'))>
                {{ __('admin.create_user_is_admin') }}
            </label>

            <button type="submit" class="mt-3">{{ __('admin.create_user') }}</button>
        </form>
    </div>

@endsection
