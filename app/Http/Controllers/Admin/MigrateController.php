<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;

/**
 * Runs any outstanding database migrations from the admin dashboard, so an
 * administrator can bring a freshly-deployed database up to date without shell
 * access. This does exactly what `php artisan migrate --force` does — it invokes
 * the framework's migrate command in-process rather than shelling out to PHP.
 */
class MigrateController extends Controller
{
    /** Run the pending database migrations from the dashboard prompt. */
    public function __invoke(): RedirectResponse
    {
        // --force skips the interactive production confirmation (there is no TTY
        // to answer it here).
        Artisan::call('migrate', ['--force' => true]);

        return redirect()->route('admin.dashboard')->with('status', __('admin.migrations_ran'));
    }
}
