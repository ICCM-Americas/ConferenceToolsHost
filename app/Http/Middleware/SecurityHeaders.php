<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generates a per-request CSP nonce (shared to every view as $cspNonce) and
 * attaches the Content-Security-Policy header to every response. Currently
 * report-only while inline script/style call sites are migrated to the
 * nonce; flip CSP_ENFORCE=true once a full click-through + Playwright pass
 * shows zero violations in storage/logs/csp.log.
 */
class SecurityHeaders
{
    /** Share a fresh nonce with the view layer and set the CSP header on the outgoing response. */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));

        View::share('cspNonce', $nonce);

        $response = $next($request);

        $headerName = config('security.csp_enforce')
            ? 'Content-Security-Policy'
            : 'Content-Security-Policy-Report-Only';

        $response->headers->set($headerName, $this->policy($nonce));

        return $response;
    }

    /** Build the CSP directive string for the given request's nonce. */
    private function policy(string $nonce): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://unpkg.com",
            "style-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net",
            // blob: is needed for the branding logo picker's local file
            // preview (URL.createObjectURL before upload) — confirmed via a
            // report-only run before this was added. data: covers the locally
            // rendered MFA enrollment QR code; no external image host is
            // allowed, so a secret can never leave in an image request.
            "img-src 'self' data: blob:",
            "font-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            'report-uri '.route('csp.report'),
        ];

        return implode('; ', $directives);
    }
}
