<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    |
    | The package registers its routes under a configurable URL prefix and
    | route-name prefix so the host application can mount the scheduler wherever
    | it likes (e.g. "/scheduler", "/conference-schedule", or the site root).
    |
    | NOTE: the package's own published views reference route names using the
    | default name prefix below ("scheduler."). If you change the name prefix,
    | publish and adjust the views accordingly.
    |
    */

    // This host mounts all scheduler routes under "/bofs" (e.g. /bofs/sessions,
    // /bofs/admin). Route NAMES keep the "scheduler." prefix so internal links
    // and the package views resolve unchanged.
    'route_prefix' => env('SCHEDULER_ROUTE_PREFIX', 'bofs'),
    'route_name_prefix' => env('SCHEDULER_ROUTE_NAME_PREFIX', 'scheduler.'),

    // Public, login-free projector page. Path is relative to the app root; this
    // host nests it under the BoFs area so it lives at "/bofs/projector".
    'public_results_path' => env('SCHEDULER_PUBLIC_RESULTS_PATH', 'bofs/projector'),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | The host app protects package routes with its own middleware. Admin
    | screens are gated by the host-defined authorization gate (see admin_gate).
    |
    */

    // This host layers its own middleware onto the package routes: "not.locked"
    // logs out users locked mid-session, and "password.current" enforces forced
    // password changes — on scheduler pages as well as the host's own.
    'web_middleware' => ['web'],
    'auth_middleware' => ['web', 'auth', 'not.locked', 'password.current'],
    'admin_middleware' => ['web', 'auth', 'not.locked', 'password.current', 'can:manage-scheduler'],

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | The package does NOT define who an admin is. The host application defines
    | this gate (Gate::define('manage-scheduler', ...)). The default
    | admin_middleware above references it via "can:manage-scheduler".
    |
    */

    'admin_gate' => env('SCHEDULER_ADMIN_GATE', 'manage-scheduler'),

    /*
    |--------------------------------------------------------------------------
    | Host user model integration
    |--------------------------------------------------------------------------
    |
    | The package stores a user identifier on its own tables and resolves the
    | host user model for relationships. It never owns the users table.
    |
    */

    'user_model' => env('SCHEDULER_USER_MODEL', 'App\\Models\\User'),
    'user_table' => env('SCHEDULER_USER_TABLE', 'users'),
    'user_display_name_attribute' => env('SCHEDULER_USER_DISPLAY_NAME', 'name'),
    'user_email_attribute' => env('SCHEDULER_USER_EMAIL', 'email'),

    /*
    |--------------------------------------------------------------------------
    | Layout & branding
    |--------------------------------------------------------------------------
    |
    | Package views extend this layout. Override it (or publish the views) to
    | match the host application's chrome. Branding within the scheduler module
    | is configurable through the admin settings screen.
    |
    */

    'layout' => env('SCHEDULER_LAYOUT', 'layouts.app'),

    // Branding and the web app manifest are host concerns in this app: branding
    // is managed under the host Admin dashboard, and the manifest is served by
    // the host at /manifest.webmanifest.

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    |
    | All package tables share this prefix to avoid collisions with host tables
    | (notably "sessions" vs Laravel's session table).
    |
    */

    'tables' => [
        'prefix' => env('SCHEDULER_TABLE_PREFIX', 'bof_'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Schedule generation
    |--------------------------------------------------------------------------
    |
    | probably_attending_unavailable: whether a user who marked a permanent
    | session as "probably attending" is treated as hard-unavailable for the
    | usual time slots it overlaps (the same as "definitely attending"). Defaults
    | to false — only "definitely attending" is a hard conflict.
    |
    */

    'scheduling' => [
        'probably_attending_unavailable' => env('BoF_SCHEDULER_PROBABLY_UNAVAILABLE', false),
    ],

];
