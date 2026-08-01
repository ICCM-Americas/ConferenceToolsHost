<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Lang;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Support\BladeTranslationLinter;
use Tests\TestCase;

/**
 * Guards UI localization: every user-facing string in a Blade view must go
 * through a translation call, and every translation key those views reference
 * must exist in the non-localized (US English) "en" lang files.
 *
 * This fails the moment someone adds hardcoded copy to a view or references a
 * key that has no en entry — covering the host app and both bundled packages.
 */
#[TestDox('UI translation coverage')]
class TranslationCoverageTest extends TestCase
{
    /** View roots scanned: the host app plus both bundled conference-tools packages. */
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
    #[TestDox('blade views contain no hardcoded user-facing strings')]
    public function blade_views_contain_no_hardcoded_user_facing_strings(): void
    {
        $offenders = [];

        foreach (BladeTranslationLinter::viewFiles($this->viewRoots()) as $file) {
            $bare = BladeTranslationLinter::bareStrings(file_get_contents($file));
            $rel = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
            foreach ($bare as $snippet) {
                $offenders[] = $rel.'  →  '.$snippet;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Hardcoded user-facing strings found in Blade views. Wrap each in a '
            ."translation call (e.g. {{ __('group.key') }}) backed by an entry in "
            ."the relevant lang/en file:\n - ".implode("\n - ", $offenders)
        );
    }

    #[Test]
    #[TestDox('referenced translation keys exist for US English')]
    public function referenced_translation_keys_exist_for_us_english(): void
    {
        // The non-localized default is US English; check keys against it.
        $this->app->setLocale('en');

        $missing = [];

        foreach (BladeTranslationLinter::viewFiles($this->viewRoots()) as $file) {
            $rel = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
            foreach (BladeTranslationLinter::referencedKeys(file_get_contents($file)) as $key) {
                if (! Lang::has($key, 'en')) {
                    $missing[] = $rel.'  →  '.$key;
                }
            }
        }

        $this->assertSame(
            [],
            $missing,
            'Translation keys referenced by views but missing from the en '
            ."(US English) lang files:\n - ".implode("\n - ", $missing)
        );
    }
}
