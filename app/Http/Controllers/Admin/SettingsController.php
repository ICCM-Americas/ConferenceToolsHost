<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Host "Site settings": application-level options the host owns itself (as
 * opposed to branding, which the branding package manages). Currently these
 * are the printed/external schedule and ICCM NAS links shown in the top
 * navigation.
 *
 * Values live in the database (the "settings" table), not in a migration: the
 * form pre-fills with the current effective value — the shipped default until
 * an admin saves — and persists whatever the admin enters.
 */
class SettingsController extends Controller
{
    /** Show the site settings form. */
    public function edit(): View
    {
        // Pre-fill the fields with the same effective values the nav renders (the
        // shipped default until an admin saves, or an empty string once cleared).
        return view('admin.settings.edit', [
            'scheduleUrl' => Setting::scheduleUrl(),
            'iccmNasUrl' => Setting::iccmNasUrl(),
        ]);
    }

    /** Save the site settings. */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'schedule_url' => ['nullable', 'string', 'url', 'max:255'],
            'iccm_nas_url' => ['nullable', 'string', 'url', 'max:255'],
        ]);

        // An omitted/empty field is stored as an empty string (a deliberate
        // "hide the link" value), distinct from an unset setting which would
        // fall back to the shipped default.
        Setting::put(Setting::SCHEDULE_URL, $validated['schedule_url'] ?? '');
        Setting::put(Setting::ICCM_NAS_URL, $validated['iccm_nas_url'] ?? '');

        return redirect()->route('admin.settings.edit')->with('status', __('admin.site_settings_saved'));
    }
}
