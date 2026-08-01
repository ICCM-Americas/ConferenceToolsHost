@extends('layouts.app')

@section('title', __('admin.dashboard'))

@section('content')
    <h1>{{ __('admin.dashboard') }}</h1>
    <p class="iccm-muted">{{ __('admin.dashboard_intro') }}</p>

    {{-- Outstanding database migrations (e.g. straight after a deploy) must be
         applied before the app is fully working, so this prompt uses the error
         (red) styling to make that obvious. Hidden once nothing is pending. --}}
    @if ($pendingMigrations > 0)
        <div class="iccm-errors">
            <h2>{{ __('admin.migrations_pending_title') }}</h2>
            <p>{{ trans_choice('admin.migrations_pending_intro', $pendingMigrations, ['count' => $pendingMigrations]) }}</p>
            <form method="POST" action="{{ route('admin.migrate') }}">
                @csrf
                <button type="submit" class="iccm-danger">{{ __('admin.run_migrations') }}</button>
            </form>
        </div>
    @endif

    <div class="iccm-grid-2">
        <div class="iccm-card">
            <h2>{{ __('admin.users') }}</h2>
            <p class="iccm-muted">{{ trans_choice(':count user|:count users', $userCount, ['count' => $userCount]) }} · {{ $adminCount }} {{ __('admin.users') }} {{ __('admin.admin_suffix') }}</p>
            <a href="{{ route('admin.users.index') }}" class="iccm-btn">{{ __('admin.manage_users') }}</a>
        </div>

        <div class="iccm-card">
            <h2>{{ __('admin.site_settings') }}</h2>
            <p class="iccm-muted">{{ __('admin.site_settings_intro') }}</p>
            <a href="{{ route('admin.settings.edit') }}" class="iccm-btn">{{ __('admin.site_settings') }}</a>
        </div>

        <div class="iccm-card">
            <h2>{{ __('admin.settings') }}</h2>
            <p class="iccm-muted">{{ __('admin.branding_intro') }}</p>
            <a href="{{ route('admin.branding.edit') }}" class="iccm-btn">{{ __('admin.settings') }}</a>
        </div>

        {{-- Registration summary, reusing the registration package's own card.
             The card (and its view namespace) only exist when the package is
             installed, so render it only then. --}}
        @if ($registrationInstalled)
            @include('registration::partials.admin-registrations-card', ['withLink' => true])
        @endif

        {{-- BoF summary card, provided by the scheduler package (mirrors the
             registration card above). Shown only when the package is installed. --}}
        @if ($bof_schedulerInstalled)
            @include('bofscheduler::partials.admin-summary-card', ['withLink' => true])
        @endif
    </div>
@endsection
