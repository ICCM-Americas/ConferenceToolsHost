<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Tests\Support\BladeTranslationLinter;

/** Unit tests for BladeTranslationLinter. */
#[TestDox('BladeTranslationLinter')]
class BladeTranslationLinterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function templates(): iterable
    {
        yield 'translated text and echoes are clean' => [
            '<p>{{ __(\'admin.save\') }}</p>', [],
        ];
        yield 'a bare text node is flagged' => [
            '<p>Hello there</p>', ['Hello there'],
        ];
        yield 'a literal title attribute is flagged' => [
            '<a title="Open the page">x</a>', ['title="Open the page"'],
        ];
        yield 'a component-bound title attribute is a PHP expression, not prose' => [
            '<x-auth-card :title="__(\'auth_ui.login\')">{{ $slot }}</x-auth-card>', [],
        ];
        yield 'a single-token placeholder is a format example, not prose' => [
            '<input placeholder="annual-meeting">', [],
        ];
        yield 'a sentence-like placeholder is flagged' => [
            '<input placeholder="Enter your name">', ['placeholder="Enter your name"'],
        ];
        yield 'directives and their nested-parenthesis arguments are stripped' => [
            "@if (\$a && foo('Bar (baz)'))\n<span>{{ \$x }}</span>\n@endif", [],
        ];
        yield 'an unclosed directive argument list consumes the rest' => [
            "@if (broken\nLeftover words", [],
        ];
        yield 'blade comments, script and style blocks are ignored' => [
            "{{-- A note --}}<script>var s = 'Words';</script><style>.a{content:'Words';}</style>", [],
        ];
    }

    #[Test]
    #[TestDox('bareStrings flags only hardcoded user-facing prose')]
    #[DataProvider('templates')]
    public function bare_strings_scan(string $template, array $expected): void
    {
        $this->assertSame($expected, BladeTranslationLinter::bareStrings($template));
    }

    #[Test]
    #[TestDox('referencedKeys collects dotted and namespaced keys but not JSON-style prose')]
    public function referenced_keys_scan(): void
    {
        $template = "{{ __('admin.save') }} {{ __('pkg::admin.title') }} {{ __('Save changes') }} {{-- __('admin.unused') --}}";

        $this->assertSame(['admin.save', 'pkg::admin.title'], BladeTranslationLinter::referencedKeys($template));
    }
}
