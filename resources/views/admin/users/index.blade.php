@extends('layouts.app')

@section('title', __('admin.users'))

@section('content')
    <p><a href="{{ route('admin.dashboard') }}">← {{ __('admin.back_to_dashboard') }}</a></p>
    <h1>{{ __('admin.users') }}</h1>

    <div class="iccm-card">
        <a href="{{ route('admin.users.create') }}" class="iccm-btn">{{ __('admin.create_user') }}</a>
    </div>

    <div class="iccm-card">
        <form method="GET" action="{{ route('admin.users.index') }}" class="iccm-form-row">
            <div class="filter-field-search"><label>{{ __('admin.search') }}</label><input type="text" name="search" value="{{ $search }}" placeholder="{{ __('admin.search_placeholder') }}"></div>
            <div class="filter-field-select">
                <label>{{ __('admin.filter') }}</label>
                <select name="filter">
                    <option value="">{{ __('admin.filter_all') }}</option>
                    <option value="admins" @selected($filter==='admins')>{{ __('admin.filter_admins') }}</option>
                    <option value="locked" @selected($filter==='locked')>{{ __('admin.filter_locked') }}</option>
                    <option value="must_change" @selected($filter==='must_change')>{{ __('admin.filter_must_change') }}</option>
                </select>
            </div>
            <button type="submit">{{ __('admin.filter') }}</button>
        </form>
    </div>

    <div class="iccm-card">
        @forelse ($users as $user)
            <div class="iccm-checkbox-row iccm-list-row iccm-list-row-loose iccm-list-row-split user-row">
                <div>
                    <div>{{ $user->name }}</div>
                    <div class="iccm-muted">{{ $user->email }}</div>
                    <div class="badges">
                        @if ($user->is_admin)<span class="iccm-pill">{{ __('admin.col_admin') }}</span>@endif
                        @if ($user->is_locked)<span class="iccm-pill">{{ __('admin.col_locked') }}</span>@endif
                        <span class="iccm-pill">{{ __('admin.col_passkeys') }}: {{ $user->passkeys_count }}</span>
                        @if ($user->hasTwoFactorEnabled())<span class="iccm-pill">{{ __('admin.col_mfa') }}</span>@endif
                        @if ($user->must_change_password)<span class="iccm-pill">{{ __('admin.col_must_change') }}</span>@endif
                    </div>
                </div>
                <a href="{{ route('admin.users.show', $user) }}" class="iccm-btn iccm-ghost">{{ __('admin.manage') }}</a>
            </div>
        @empty
            <p class="iccm-muted">{{ __('admin.no_users') }}</p>
        @endforelse
        <div class="mt-3">{{ $users->links('pagination::bootstrap-4') }}</div>
    </div>
@endsection
