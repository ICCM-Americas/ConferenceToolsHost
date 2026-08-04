<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as BaseTrustProxies;

/**
 * Trusts the reverse proxies named in config/security.php.
 *
 * The list is read per request rather than passed to trustProxies(), whose
 * callback in bootstrap/app.php runs before the configuration is loaded.
 */
class TrustProxies extends BaseTrustProxies
{
    /** The proxies to trust on this request. */
    protected function proxies()
    {
        return config('security.trusted_proxies') ?: parent::proxies();
    }
}
