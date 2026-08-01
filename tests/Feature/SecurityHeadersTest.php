<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/** Feature tests for the Content-Security-Policy header. */
#[TestDox('Security headers')]
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('every response carries a report-only CSP while enforcement is off')]
    public function response_carries_report_only_csp(): void
    {
        $response = $this->get(route('login'));

        $response->assertHeader('Content-Security-Policy-Report-Only');
        $this->assertFalse($response->headers->has('Content-Security-Policy'));

        $policy = $response->headers->get('Content-Security-Policy-Report-Only');
        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString('https://cdn.jsdelivr.net', $policy);
        $this->assertStringContainsString('https://unpkg.com', $policy);
        $this->assertStringContainsString(route('csp.report'), $policy);
    }

    #[Test]
    #[TestDox('images may only come from this origin, so no secret can leave in an image request')]
    public function images_are_restricted_to_local_sources(): void
    {
        $policy = $this->get(route('login'))->headers->get('Content-Security-Policy-Report-Only');

        $this->assertStringContainsString("img-src 'self' data: blob:;", $policy);
        $this->assertStringNotContainsString('qrserver', $policy);
    }

    #[Test]
    #[TestDox('the nonce is fresh on every request, not hardcoded')]
    public function nonce_is_per_request(): void
    {
        $first = $this->get(route('login'))->headers->get('Content-Security-Policy-Report-Only');
        $second = $this->get(route('login'))->headers->get('Content-Security-Policy-Report-Only');

        $this->assertNotSame($first, $second);
    }

    #[Test]
    #[TestDox('the rendered nonce attribute matches the header nonce')]
    public function rendered_nonce_matches_header(): void
    {
        $response = $this->get(route('login'));

        preg_match("/'nonce-([^']+)'/", $response->headers->get('Content-Security-Policy-Report-Only'), $match);
        $this->assertNotEmpty($match, 'no nonce found in the CSP header');

        $response->assertSee('nonce="'.$match[1].'"', false);
    }

    #[Test]
    #[TestDox('enforcement can be switched on via config')]
    public function enforcement_switches_the_header_name(): void
    {
        config(['security.csp_enforce' => true]);

        $response = $this->get(route('login'));

        $response->assertHeader('Content-Security-Policy');
        $this->assertFalse($response->headers->has('Content-Security-Policy-Report-Only'));
    }
}
