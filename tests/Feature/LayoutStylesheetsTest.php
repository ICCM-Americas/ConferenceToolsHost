<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * The layout is the only place stylesheets are linked, so a rename or a
 * dropped link here unstyles the whole application at once.
 */
#[TestDox('Layout stylesheets')]
class LayoutStylesheetsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return iterable<string, array{string}>
     */
    public static function alwaysLinked(): iterable
    {
        yield 'the shared design system' => ['vendor/branding/css/iccm.css'];
        yield 'the shared layout utilities' => ['vendor/branding/css/iccm-utilities.css'];
        yield "the host's own screens" => ['css/app.css'];
    }

    #[Test]
    #[TestDox('every page links the shared and host stylesheets')]
    #[DataProvider('alwaysLinked')]
    public function every_page_links_the_shared_and_host_stylesheets(string $path): void
    {
        $this->get(route('login'))->assertOk()->assertSee(asset($path), false);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function packageStylesheets(): iterable
    {
        yield 'registration' => ['registrationInstalled', 'vendor/registration/css/registration.css'];
        yield 'bof-scheduler' => ['bof_schedulerInstalled', 'vendor/bof-scheduler/css/bof-scheduler.css'];
    }

    #[Test]
    #[TestDox('a package stylesheet is linked only while that package is installed')]
    #[DataProvider('packageStylesheets')]
    public function package_stylesheet_is_linked_only_while_installed(string $flag, string $path): void
    {
        // Both packages are installed in the test suite, so the flag is true.
        $this->get(route('login'))->assertOk()->assertSee(asset($path), false);

        View::share($flag, false);

        $this->get(route('login'))->assertOk()->assertDontSee(asset($path), false);
    }

    #[Test]
    #[TestDox('the brand colors are the only CSS the layout writes inline')]
    public function brand_colors_are_the_only_inline_css(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $html, $blocks);

        $this->assertCount(1, $blocks[1]);
        $this->assertStringContainsString('--color-primary:', $blocks[1][0]);
    }
}
