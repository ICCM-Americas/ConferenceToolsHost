@use('ConferenceTools\Branding\Support\Asset')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Mobile web app / "add to home screen" support --}}
    <meta name="theme-color" content="{{ $branding->color('primary') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ $branding->shortName() }}">
    <link rel="manifest" href="{{ route('manifest') }}">
    @if ($branding->logoUrl())
        <link rel="apple-touch-icon" href="{{ $branding->logoUrl() }}">
    @endif

    <title>@yield('title', $branding->siteName()) — {{ $branding->siteName() }}</title>

    {{-- Bootstrap powers the package screens written with Bootstrap classes
         (e.g. registration's admin forms). Loaded before the conference-tools
         stylesheets below so the shared chrome, components and branding
         overrides win on any shared selectors. --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">

    {{-- The shared design system and layout utilities, owned by the branding
         package and published to public/vendor/branding/css. Every screen in
         the app and in the conference-tools packages is styled from these. --}}
    <link rel="stylesheet" href="{{ Asset::url('vendor/branding/css/iccm.css') }}">
    <link rel="stylesheet" href="{{ Asset::url('vendor/branding/css/iccm-utilities.css') }}">

    {{-- The host's own screens (auth, profile, user admin). --}}
    <link rel="stylesheet" href="{{ Asset::url('css/app.css') }}">

    {{-- Each optional package ships the CSS for its own screens; link it only
         when that package is installed (same flags the nav gates on). --}}
    @if ($bof_schedulerInstalled)
        <link rel="stylesheet" href="{{ Asset::url('vendor/bof-scheduler/css/bof-scheduler.css') }}">
    @endif
    @if ($registrationInstalled)
        <link rel="stylesheet" href="{{ Asset::url('vendor/registration/css/registration.css') }}">
    @endif

    {{-- The one rule set that cannot live in a stylesheet: the brand colors are
         per-install values read from the branding database, and every rule in
         the sheets above resolves them through these custom properties. Last so
         an admin's configured colors win over any default a sheet might set. --}}
    <style nonce="{{ $cspNonce }}">
        :root {
            --color-primary: {{ $branding->color('primary') }};
            --color-secondary: {{ $branding->color('secondary') }};
            --color-bg: {{ $branding->color('background') }};
            --color-text: {{ $branding->color('text') }};
            --color-on-primary: {{ $branding->logoColor() }};
            --color-on-secondary: {{ $branding->onColor('secondary') }};
        }
    </style>
    @stack('head')
</head>
<body>
    <header class="iccm-app">
        <a href="{{ $branding->url() }}" target="_blank" rel="noopener" class="iccm-brand">
            @if ($branding->logoUrl())
                <img src="{{ $branding->logoUrl() }}" alt="" class="iccm-logo">
            @endif
            <span>{{ $branding->siteName() }}</span>
        </a>

        {{-- Module label shown after the branding title, so the BoFs and
             Registration sections each carry their own name in the top bar. --}}
        @php($navSection = (string) (optional(request()->route())->getName() ?? ''))
        @if (str_starts_with($navSection, 'registration.'))
            <span class="iccm-section-title">{{ __('nav.registration') }}</span>
        @elseif (str_starts_with($navSection, 'scheduler.'))
            <span class="iccm-section-title">{{ __('nav.bofs') }}</span>
        @endif

        <nav class="iccm-app">
            @auth
                @if (auth()->user()->isAdmin())
                    {{-- BoF scheduler links live under their own "BoFs" menu, keeping
                         them clearly separate from host (non-BoF) features. Shown
                         only when the bof-scheduler package is installed. --}}
                    @if ($bof_schedulerInstalled)
                    <details class="iccm-menu" name="nav-menu">
                        <summary>{{ __('nav.bofs') }}</summary>
                        <div class="iccm-menu-items">
                            <a href="{{ route('scheduler.admin.dashboard') }}">{{ __('admin.dashboard') }}</a>
                            <a href="{{ route('scheduler.sessions.index') }}">{{ __('nav.nominate') }}</a>
                            <a href="{{ route('scheduler.results.public') }}">{{ __('nav.public') }}</a>
                        </div>
                    </details>
                    @endif
                    {{-- Registration links live under their own "Registration" menu, keeping
                         them clearly separate from host (non-BoF) features. Shown
                         only when the registration package is installed. --}}
                    @if ($registrationInstalled)
                    <details class="iccm-menu" name="nav-menu">
                        <summary>{{ __('nav.registration') }}</summary>
                        <div class="iccm-menu-items">
                            <a href="{{ route('registration.admin.dashboard') }}">{{ __('admin.dashboard') }}</a>
                        </div>
                    </details>
                    @endif
                    {{-- Host (non-BoF) admin: branding and user management. --}}
                    <details class="iccm-menu" name="nav-menu">
                        <summary>{{ __('admin.admin') }}</summary>
                        <div class="iccm-menu-items">
                            <a href="{{ route('admin.dashboard') }}">{{ __('nav.dashboard') }}</a>
                            <a href="{{ route('admin.settings.edit') }}">{{ __('admin.site_settings') }}</a>
                            <a href="{{ route('admin.branding.edit') }}">{{ __('admin.settings') }}</a>
                            <a href="{{ route('admin.users.index') }}">{{ __('admin.users') }}</a>
                        </div>
                    </details>
                @elseif ($bof_schedulerInstalled)
                    <a href="{{ route('scheduler.landing') }}">{{ __('nav.bofs') }}</a>
                @endif
                {{-- Printed/external event schedule (hosted elsewhere). The URL is
                     an admin-editable host setting; when blank the link is hidden. --}}
                @if ($scheduleUrl)
                    <a target="_blank" href="{{ $scheduleUrl }}" rel="noopener">{{ __('nav.schedule') }}</a>
                @endif
                {{-- ICCM NAS (hosted elsewhere). Same admin-editable host
                     setting pattern as the schedule link above. --}}
                @if ($iccmNasUrl)
                    <a target="_blank" href="{{ $iccmNasUrl }}" rel="noopener">{{ __('nav.iccm_nas') }}</a>
                @endif
                {{-- Account hub (booking, profile, sign out) — shown as the generic
                     "person" bust icon, linking to the dashboard. Replaces the old
                     Profile/Booking/Logout links. --}}
                <a href="{{ route('home') }}" class="iccm-account" aria-label="{{ __('nav.account') }}" title="{{ __('nav.account') }}">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-5.33 0-9 2.67-9 6v2h18v-2c0-3.33-3.67-6-9-6Z"/>
                    </svg>
                </a>
            @else
                @if ($bof_schedulerInstalled)
                    <a href="{{ route('scheduler.results.public') }}">{{ __('nav.results') }}</a>
                @endif
                <a href="{{ route('login') }}">{{ __('nav.login') }}</a>
                <a href="{{ route('register') }}">{{ __('nav.register') }}</a>
            @endauth
        </nav>
    </header>

    <main>
        @if (session('status'))
            <div class="iccm-flash">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="iccm-errors">
                <ul class="iccm-errors-list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <script nonce="{{ $cspNonce }}">
        // Admin section menus open/close on hover, in addition to the native
        // click/tap toggle (so they also work on touch devices).
        document.querySelectorAll('nav.iccm-app details.iccm-menu').forEach(function (menu) {
            menu.addEventListener('mouseenter', function () { menu.open = true; });
            menu.addEventListener('mouseleave', function () { menu.open = false; });
        });
    </script>
</body>
</html>
