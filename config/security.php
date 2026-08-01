<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CSP Enforcement
    |--------------------------------------------------------------------------
    |
    | While false, the Content-Security-Policy is sent as
    | Content-Security-Policy-Report-Only: violations are logged via
    | /csp-report but nothing is actually blocked. Flip to true once a full
    | manual click-through plus a clean Playwright run shows zero violations.
    |
    */

    'csp_enforce' => env('CSP_ENFORCE', false),

    /*
    |--------------------------------------------------------------------------
    | Login / Two-Factor Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Attempts per minute allowed on POST /login and /two-factor-challenge,
    | keyed by account + IP (see AppServiceProvider::boot()). The Playwright
    | suite raises this via LOGIN_RATE_LIMIT (see tests/ui/support/env.js) —
    | it drives ~30 spec files against a handful of fixed seeded accounts on
    | one long-lived server process, which would otherwise trip the same
    | 5/minute limit real credential guessing is meant to stop.
    |
    */

    'login_rate_limit' => (int) env('LOGIN_RATE_LIMIT', 5),

];
