<?php

namespace Tests\Feature;

use ConferenceTools\Branding\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/** Feature tests for Web app manifest. */
#[TestDox('Web app manifest')]
class ManifestTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('serves a manifest with the configured branding and bundled icons')]
    public function serves_manifest_with_default_icons(): void
    {
        Setting::put('branding.site_name', 'My Event');

        $response = $this->get(route('manifest'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/manifest+json');
        $response->assertJsonPath('name', 'My Event');
        $response->assertJsonPath('display', 'standalone');
        // Falls back to bundled icons (one of which is maskable).
        $response->assertJsonPath('icons.1.purpose', 'any maskable');
    }

    #[Test]
    #[TestDox('uses the configured logo for the manifest icons when one is set')]
    public function uses_configured_logo(): void
    {
        $data = base64_encode('fake-image-bytes');
        Setting::put('branding.logo_data', $data);
        Setting::put('branding.logo_mime', 'image/webp');

        $response = $this->get(route('manifest'));

        $response->assertOk();
        $response->assertJsonPath('icons.0.purpose', 'any');
        $response->assertJsonPath('icons.0.type', 'image/webp');
        $this->assertSame('data:image/webp;base64,'.$data, $response->json('icons.0.src'));
    }

    #[Test]
    #[TestDox('truncates a long site name into the short name')]
    public function truncates_short_name(): void
    {
        Setting::put('branding.site_name', 'An Extremely Long Event Name');

        $this->get(route('manifest'))->assertJsonPath('short_name', 'An Extremely');
    }
}
