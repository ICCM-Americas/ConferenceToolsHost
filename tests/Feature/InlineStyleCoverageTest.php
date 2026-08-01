<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Support\BladeInlineStyleLinter;
use Tests\Support\BladeTranslationLinter;
use Tests\TestCase;

/**
 * Guards the stylesheet boundary: CSS belongs in a published stylesheet, not in
 * a Blade view. The only thing a view may still declare is a CSS custom
 * property, whose value comes from the branding or badge-size settings and so
 * cannot live in a static file.
 *
 * This fails the moment someone writes a rule back into a view — covering the
 * host app and all three bundled packages.
 */
#[TestDox('Inline style coverage')]
class InlineStyleCoverageTest extends TestCase
{
    /** View roots scanned: the host app plus all three bundled conference-tools packages. */
    private function viewRoots(): array
    {
        return array_values(array_filter([
            base_path('resources/views'),
            base_path('packages/conference-tools/branding/resources/views'),
            base_path('packages/conference-tools/bof-scheduler/resources/views'),
            base_path('packages/conference-tools/registration/resources/views'),
        ], 'is_dir'));
    }

    #[Test]
    #[TestDox('blade views declare no CSS beyond custom properties')]
    public function blade_views_declare_no_css_beyond_custom_properties(): void
    {
        $offenders = [];

        foreach (BladeTranslationLinter::viewFiles($this->viewRoots()) as $file) {
            $rel = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
            foreach (BladeInlineStyleLinter::offenses(file_get_contents($file)) as $offense) {
                $offenders[] = $rel.'  →  '.$offense;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'CSS found in Blade views. Move each rule to the stylesheet for its '
            .'package (resources/css/*.css, or public/css/app.css for the host); '
            .'if the value is per-install, emit it as a CSS custom property the '
            ."stylesheet reads:\n - ".implode("\n - ", $offenders)
        );
    }
}
