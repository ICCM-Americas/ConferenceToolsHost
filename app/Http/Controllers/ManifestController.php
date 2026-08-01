<?php

namespace App\Http\Controllers;

use ConferenceTools\Branding\Services\BrandingService;
use Illuminate\Http\JsonResponse;

/**
 * Serves the web app manifest so the site can be installed to a mobile home
 * screen. Uses the branding configuration from the conference-tools/branding
 * package (installed alongside the host). The manifest itself is a host concern.
 */
class ManifestController extends Controller
{
    /** The branded web app manifest as JSON. */
    public function __invoke(BrandingService $branding): JsonResponse
    {
        $manifest = [
            'name' => $branding->siteName(),
            'short_name' => $branding->shortName(),
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'theme_color' => $branding->color('primary'),
            'background_color' => $branding->color('background'),
            'icons' => $this->icons($branding),
        ];

        return response()->json($manifest)
            ->header('Content-Type', 'application/manifest+json');
    }

    /**
     * @return list<array<string, string>>
     */
    private function icons(BrandingService $branding): array
    {
        // Prefer the configured logo; fall back to bundled PWA icons.
        $logo = $branding->logoUrl();

        if ($logo) {
            $type = $branding->logoMime() ?? 'image/png';

            return [
                ['src' => $logo, 'sizes' => '192x192', 'type' => $type, 'purpose' => 'any'],
                ['src' => $logo, 'sizes' => '512x512', 'type' => $type, 'purpose' => 'any'],
            ];
        }

        return [
            ['src' => asset('icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => asset('icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
        ];
    }
}
