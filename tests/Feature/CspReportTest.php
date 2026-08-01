<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/** Feature tests for the CSP violation report endpoint. */
#[TestDox('CSP violation reports')]
class CspReportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('a violation report is accepted and logged')]
    public function violation_report_is_accepted_and_logged(): void
    {
        Log::shouldReceive('channel')->once()->with('csp')->andReturnSelf();
        Log::shouldReceive('warning')->once()->with('CSP violation', \Mockery::type('array'));

        $this->postJson(route('csp.report'), [
            'csp-report' => [
                'document-uri' => 'https://example.test/admin',
                'violated-directive' => 'script-src',
                'blocked-uri' => 'https://evil.example',
            ],
        ])->assertNoContent();
    }

    #[Test]
    #[TestDox('the report endpoint does not require CSRF or authentication')]
    public function report_endpoint_is_unauthenticated_and_csrf_exempt(): void
    {
        // No actingAs(), no _token — this must still succeed, since browsers
        // POST violation reports directly with neither.
        $this->post(route('csp.report'), ['csp-report' => ['blocked-uri' => 'inline']])
            ->assertNoContent();
    }

    #[Test]
    #[TestDox('the report endpoint is throttled')]
    public function report_endpoint_is_throttled(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->post(route('csp.report'), ['csp-report' => []])->assertNoContent();
        }

        $this->post(route('csp.report'), ['csp-report' => []])->assertStatus(429);
    }
}
