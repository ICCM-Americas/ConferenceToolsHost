<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | This host layers its own middleware onto the package's admin route:
    | "not.locked" logs out users locked mid-session, and "password.current"
    | enforces forced password changes — matching config/bofscheduler.php and
    | config/registration.php.
    |
    */

    'admin_middleware' => ['web', 'auth', 'not.locked', 'password.current', 'can:manage-branding'],

];
