@extends('layouts.app')

@section('title', __('nav.account'))

@section('content')
    <h1>{{ __('home.heading', ['name' => auth()->user()->name]) }}</h1>
    <br/>

    {{-- Registration lives in the registration package; only offer it when
         the package is installed. Its state (registered or not, open or
         not, paid or not, group leader or not) drives which of several
         cards this renders. --}}
    @if ($registrationInstalled)
    <div class="iccm-card">
        <h2>{{ __('nav.your_registration') }}</h2>
        @if (auth()->user()->group)
            {{-- Registered: always offer a view; a group leader also sees
                 who's in their group and whether they've registered yet. --}}
            <p class="iccm-muted">{{ __('home.registration_registered_intro') }}</p>
            <a href="{{ route('registration.mine') }}" class="iccm-btn">{{ __('home.view_registration') }}</a>

            @if (auth()->user()->is_group_admin)
                <div class="mt-3">
                    <strong>{{ __('home.group_members_heading') }}</strong>
                    @if ($registrationPendingInvites->isEmpty() && auth()->user()->group->users->count() <= 1)
                        <p class="iccm-muted">{{ __('home.group_members_none') }}</p>
                    @else
                        <ul>
                            @foreach (auth()->user()->group->users as $member)
                                @continue($member->is(auth()->user()))
                                <li>{{ $member->name }} — {{ __('home.group_member_registered') }}</li>
                            @endforeach
                            @foreach ($registrationPendingInvites as $invite)
                                <li>{{ $invite->registrationAnswers()->value('group_member_name') }} — {{ __('home.group_member_not_registered') }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            @if ($registrationOpen && ! auth()->user()->payment?->is_paid)
                <a href="{{ route('registration.mine.edit') }}" class="iccm-btn">{{ __('home.modify_registration') }}</a>
            @else
                <p class="iccm-muted">{{ __('home.contact_admin_intro') }}</p>
                @if ($registrationAdminEmail)
                    <a href="mailto:{{ $registrationAdminEmail }}" class="iccm-btn iccm-secondary">{{ __('home.email_administrator') }}</a>
                @endif
            @endif
        @elseif ($registrationOpen)
            {{-- Not registered, registration open: the usual path. --}}
            <p class="iccm-muted">{{ __('home.booking_intro') }}</p>
            <a href="{{ route('registration.register') }}" class="iccm-btn">{{ __('nav.register') }}</a>
        @else
            {{-- Not registered, not open: an admin-authored message covers
                 both "opens soon" (with a countdown) and "already closed". --}}
            <p class="iccm-muted">{{ $registrationHomeMessageBody }}</p>
        @endif
    </div>
    @endif

    <div class="iccm-card">
        <h2>{{ __('nav.profile') }}</h2>
        <p class="iccm-muted">{{ __('home.profile_intro') }}</p>
        <a href="{{ route('profile.edit') }}" class="iccm-btn">{{ __('nav.profile') }}</a>
    </div>

    <div class="iccm-card">
        <h2>{{ __('nav.logout') }}</h2>
        <p class="iccm-muted">{{ __('home.logout_intro') }}</p>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="iccm-ghost">{{ __('nav.logout') }}</button>
        </form>
    </div>
@endsection
