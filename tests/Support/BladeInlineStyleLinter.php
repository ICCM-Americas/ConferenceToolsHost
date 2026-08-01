<?php

namespace Tests\Support;

/**
 * Finds CSS that has been written into a Blade view instead of a stylesheet.
 *
 * Two rules, both enforced structurally rather than by an allowlist:
 * no `style="…"` attributes at all, and a `<style>` block may declare nothing
 * but CSS custom properties — the one thing a static stylesheet cannot hold,
 * because its values come from the database per install.
 */
class BladeInlineStyleLinter
{
    /** Descriptions of every inline-style offense in one view's source. */
    public static function offenses(string $source): array
    {
        $source = self::withoutComments($source);

        return array_merge(self::attributeOffenses($source), self::blockOffenses($source));
    }

    /** Blade and CSS comments, which may legitimately discuss inline styles. */
    private static function withoutComments(string $source): string
    {
        $source = preg_replace('/\{\{--.*?--\}\}/s', ' ', $source);

        return preg_replace('#/\*.*?\*/#s', ' ', $source);
    }

    /** Every `style="…"` attribute, which belongs in a stylesheet or a data-* attribute. */
    private static function attributeOffenses(string $source): array
    {
        preg_match_all('/\bstyle\s*=\s*(["\']).*?\1/is', $source, $matches);

        return array_map(
            fn (string $attribute): string => 'style attribute: '.self::excerpt($attribute),
            $matches[0]
        );
    }

    /** Every declaration in a `<style>` block that is not a custom property. */
    private static function blockOffenses(string $source): array
    {
        preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $source, $blocks);

        return array_merge(...array_map(self::declarationOffenses(...), $blocks[1]), ...[[]]);
    }

    /** The non-custom-property declarations found in one `<style>` block's body. */
    private static function declarationOffenses(string $body): array
    {
        // Blade echoes hold braces of their own, which would otherwise be read
        // as rule delimiters.
        $body = preg_replace('/\{\{.*?\}\}/s', 'BLADE', $body);

        $offenses = [];

        preg_match_all('/\{([^{}]*)\}/s', $body, $rules);

        foreach ($rules[1] as $rule) {
            foreach (self::properties($rule) as $property) {
                if (! str_starts_with($property, '--')) {
                    $offenses[] = 'style block declares: '.$property;
                }
            }
        }

        return $offenses;
    }

    /** The property names declared in one rule body. */
    private static function properties(string $rule): array
    {
        preg_match_all('/(?:^|;)\s*([A-Za-z-][\w-]*)\s*:/', $rule, $matches);

        return $matches[1];
    }

    /** A single-line, length-capped version of a match, for the failure message. */
    private static function excerpt(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', trim($text));

        return mb_strlen($text) > 80 ? mb_substr($text, 0, 77).'...' : $text;
    }
}
