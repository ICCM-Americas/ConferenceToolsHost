<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/** Feature tests for the configured reverse proxies behind App\Http\Middleware\TrustProxies. */
#[TestDox('Trusted proxies')]
class TrustedProxiesTest extends TestCase
{
    use RefreshDatabase;

    /** The configured proxy list, and the origin a request forwarded from 127.0.0.1 then generates. */
    public static function proxyLists(): array
    {
        return [
            'none configured' => [null, 'http://localhost'],
            'the forwarding proxy' => ['127.0.0.1', 'https://demo.example.test'],
            'a different proxy' => ['192.0.2.7', 'http://localhost'],
            'one of several' => ['192.0.2.7, 127.0.0.1', 'https://demo.example.test'],
            'every proxy' => ['*', 'https://demo.example.test'],
        ];
    }

    #[Test]
    #[DataProvider('proxyLists')]
    #[TestDox('forwarded scheme and host shape the generated URLs only for a configured proxy')]
    public function forwarded_headers_are_honored_only_for_configured_proxies(?string $proxies, string $origin): void
    {
        config(['security.trusted_proxies' => $proxies]);

        $response = $this->get(route('login'), [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'demo.example.test',
        ]);

        $policy = $response->headers->get('Content-Security-Policy-Report-Only');

        $this->assertStringContainsString('report-uri '.$origin.'/csp-report', $policy);
    }
}
