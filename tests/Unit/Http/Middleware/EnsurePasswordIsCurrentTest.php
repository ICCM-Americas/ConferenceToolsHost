<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\EnsurePasswordIsCurrent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/** Unit tests for EnsurePasswordIsCurrent middleware. */
#[TestDox('EnsurePasswordIsCurrent middleware')]
class EnsurePasswordIsCurrentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('a guest (no authenticated user) passes straight through')]
    public function guest_passes_through(): void
    {
        // The forced-password check only applies to authenticated users; with no
        // user the middleware must fall through to the next handler untouched.
        $next = new Response('next');

        $response = (new EnsurePasswordIsCurrent)->handle(Request::create('/'), fn () => $next);

        $this->assertSame($next, $response);
    }

    #[Test]
    #[TestDox('a flagged user on a request with no matched route is still redirected')]
    public function flagged_user_without_a_route_is_redirected(): void
    {
        // Exercises the null-safe route() branch: when no route is bound the
        // route name resolves to null, which is not in the allow-list, so a
        // flagged user is still sent to the forced-change screen.
        $user = User::factory()->create(['must_change_password' => true]);
        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        $response = (new EnsurePasswordIsCurrent)->handle($request, fn () => new Response('next'));

        $this->assertTrue($response->isRedirect(route('password.forced.edit')));
    }
}
