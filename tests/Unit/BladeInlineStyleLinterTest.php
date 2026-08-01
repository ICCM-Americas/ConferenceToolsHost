<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Tests\Support\BladeInlineStyleLinter;

/** Unit tests for BladeInlineStyleLinter. */
#[TestDox('BladeInlineStyleLinter')]
class BladeInlineStyleLinterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function templates(): iterable
    {
        yield 'markup with only class attributes is clean' => [
            '<div class="iccm-row"><span class="iccm-muted">x</span></div>', [],
        ];
        yield 'a style attribute is flagged' => [
            '<div style="color: red">x</div>', ['style attribute: style="color: red"'],
        ];
        yield 'a single-quoted style attribute is flagged' => [
            "<div style='color: red'>x</div>", ["style attribute: style='color: red'"],
        ];
        yield 'a style block of custom properties only is allowed' => [
            '<style>:root { --color-primary: #123456; --color-bg: #fff; }</style>', [],
        ];
        yield 'custom properties interpolated from Blade are allowed' => [
            '<style>.sheet { --badge-w: {{ $w }}mm; }</style>', [],
        ];
        yield 'a rule in a style block is flagged' => [
            '<style>.card { padding: 1rem; }</style>', ['style block declares: padding'],
        ];
        yield 'every non-custom declaration in a rule is reported' => [
            '<style>.a { margin: 0; color: red; --ok: 1; }</style>',
            ['style block declares: margin', 'style block declares: color'],
        ];
        yield 'declarations nested in an at-rule are flagged' => [
            '<style>@media print { .a { display: none; } }</style>', ['style block declares: display'],
        ];
        yield 'blade comments discussing inline styles are ignored' => [
            '{{-- never use a style="color:red" attribute here --}}<p>x</p>', [],
        ];
        yield 'css comments inside a block are ignored' => [
            '<style>:root { /* padding: 1rem was here */ --x: 1; }</style>', [],
        ];
        yield 'a nonce attribute on the style tag is not a declaration' => [
            '<style nonce="abc123">:root { --x: 1; }</style>', [],
        ];
    }

    #[Test]
    #[TestDox('offenses flags style attributes and any non-custom-property declaration')]
    #[DataProvider('templates')]
    public function offenses_scan(string $template, array $expected): void
    {
        $this->assertSame($expected, BladeInlineStyleLinter::offenses($template));
    }
}
