<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Generic key/value settings store owned by the host application, backing the
 * admin "Site settings" screen. Distinct from the branding package's own
 * "branding_settings" table so the two never collide.
 *
 * Reads are guarded against the table not existing yet: on a deployment whose
 * migrations have not been run, every getter falls back to its default instead
 * of fataling. That guard also keeps us clear of the cache store (itself a
 * database table here) before migrations create it.
 */
class Setting extends Model
{
    protected $table = 'settings';

    protected $fillable = ['key', 'value'];

    /**
     * Setting key for the printed/external event schedule link shown in the top
     * navigation. Its value lives entirely in the database (populated by an
     * administrator, or once by an operator on the initial deploy) — there is no
     * code default. An empty value, or no row at all, hides the link.
     */
    public const SCHEDULE_URL = 'nav.schedule_url';

    /**
     * Setting key for the ICCM NAS link shown in the top navigation. Same
     * lifecycle as SCHEDULE_URL: database-only, no code default, empty hides
     * the link.
     */
    public const ICCM_NAS_URL = 'nav.iccm_nas_url';

    /**
     * The schedule link as stored (empty string when unset or deliberately
     * cleared, which hides the link). Single source of truth for both the nav
     * and the settings form.
     */
    public static function scheduleUrl(): string
    {
        return (string) static::get(self::SCHEDULE_URL, '');
    }

    /**
     * The ICCM NAS link as stored (empty string when unset or deliberately
     * cleared, which hides the link). Single source of truth for both the nav
     * and the settings form.
     */
    public static function iccmNasUrl(): string
    {
        return (string) static::get(self::ICCM_NAS_URL, '');
    }

    /** Read a setting, falling back to the default when unset or before migrations have run. */
    public static function get(string $key, mixed $default = null): mixed
    {
        // Before migrations have run the table (and the database cache table)
        // don't exist yet; fall back to the default rather than querying them.
        if (! Schema::hasTable((new static)->getTable())) {
            return $default;
        }

        $value = Cache::rememberForever('setting.'.$key, fn () => static::query()->where('key', $key)->value('value'));

        // A stored empty string is a deliberate, meaningful value (e.g. "hide
        // this link") and must not be replaced by the default — only a missing
        // row (null) falls back.
        return $value ?? $default;
    }

    /** Store a setting and drop its cached value. */
    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('setting.'.$key);
    }
}
