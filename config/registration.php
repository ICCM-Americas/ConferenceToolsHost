<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    |
    | The registration package mounts every route under a single, configurable
    | URL prefix; the admin console sits at "{prefix}/admin". Keeping the whole
    | module under one prefix lets the host expose it behind a short host name
    | (e.g. one pointing at "/registration"), mirroring how the scheduler lives
    | under "/bofs".
    |
    | Route NAMES keep the "registration." prefix so internal links and the
    | package views resolve unchanged.
    |
    */

    'route_prefix' => env('REGISTRATION_ROUTE_PREFIX', 'registration'),
    'route_name_prefix' => env('REGISTRATION_ROUTE_NAME_PREFIX', 'registration.'),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | This host layers its own middleware onto the package routes: "not.locked"
    | logs out users locked mid-session, and "password.current" enforces forced
    | password changes — matching config/bofscheduler.php.
    |
    */

    'auth_middleware' => ['web', 'auth', 'not.locked', 'password.current'],
    'admin_middleware' => ['web', 'auth', 'not.locked', 'password.current', 'can:manage-registration'],

    /*
    |--------------------------------------------------------------------------
    | Layout & branding
    |--------------------------------------------------------------------------
    |
    | Registration screens extend this layout. This host points it at its own
    | chrome (layouts.app) so the registration pages — public and admin — share
    | the application's branding and navigation, just like the scheduler. That
    | layout loads Bootstrap (the registration views are Bootstrap-classed) and
    | overrides Bootstrap's colors with the host branding.
    |
    */

    'layout' => env('REGISTRATION_LAYOUT', 'layouts.app'),

];
