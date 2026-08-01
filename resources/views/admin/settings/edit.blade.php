@extends('layouts.app')

@section('title', __('admin.site_settings'))

@section('content')
    <p><a href="{{ route('admin.dashboard') }}">← {{ __('admin.back_to_dashboard') }}</a></p>
    <h1>{{ __('admin.site_settings') }}</h1>
    <p class="iccm-muted">{{ __('admin.site_settings_intro') }}</p>

    <div class="iccm-card">
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            @method('PUT')

            <label for="schedule_url">{{ __('admin.schedule_url') }}</label>
            <input id="schedule_url" type="url" name="schedule_url" value="{{ old('schedule_url', $scheduleUrl) }}" autofocus>
            <p class="iccm-muted">{{ __('admin.schedule_url_hint') }}</p>

            <label for="iccm_nas_url">{{ __('admin.iccm_nas_url') }}</label>
            <input id="iccm_nas_url" type="url" name="iccm_nas_url" value="{{ old('iccm_nas_url', $iccmNasUrl) }}">
            <p class="iccm-muted">{{ __('admin.iccm_nas_url_hint') }}</p>

            <button type="submit" class="mt-3">{{ __('admin.save') }}</button>
        </form>
    </div>
@endsection
