<?php

namespace Tests\Support;

use Tests\Feature\TranslationCoverageTest;

/**
 * Static helpers for scanning Blade templates for hardcoded, untranslated
 * user-facing text, and for collecting the translation keys they reference.
 *
 * Kept free of framework dependencies so the scanning logic stays easy to
 * reason about and can be exercised directly. Consumed by
 * {@see TranslationCoverageTest}.
 *
 * The bare-string scan is deliberately conservative: it removes everything that
 * is unambiguously not user-facing prose (Blade comments, @php blocks, <script>
 * and <style> blocks, {{ }} / {!! !!} echoes, Blade directives and their
 * arguments, and HTML tags) and then flags any alphabetic text left behind in a
 * text node or in a prose attribute (title/aria-label/alt, and multi-word
 * placeholders). Single-token placeholder values (e.g. "annual-meeting", "-50")
 * are treated as format examples and ignored.
 */
class BladeTranslationLinter
{
    /** Recursively collect every *.blade.php file under the given directories. */
    public static function viewFiles(array $dirs): array
    {
        $files = [];
        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                    $files[] = $file->getPathname();
                }
            }
        }
        sort($files);

        return $files;
    }

    /**
     * Hardcoded user-facing strings in a Blade template that are not wrapped in
     * a translation call. Returns a de-duplicated list of offending snippets.
     *
     * @return list<string>
     */
    public static function bareStrings(string $contents): array
    {
        // Strip everything that can't be user-facing prose, but keep HTML tags
        // for now so their attributes can be inspected.
        $s = preg_replace('/\{\{--.*?--\}\}/s', ' ', $contents);
        $s = preg_replace('/@php\b.*?@endphp/s', ' ', $s);
        $s = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $s);
        $s = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $s);
        $s = preg_replace('/\{!!.*?!!\}/s', ' ', $s);
        $s = preg_replace('/\{\{.*?\}\}/s', ' ', $s);
        // Blade component bound attributes (:title="__('key')") hold PHP
        // expressions, not literal prose.
        $s = preg_replace('/(?<=[\s<]):{1,2}[\w-]+\s*=\s*(["\'])(?:(?!\1).)*\1/s', ' ', $s);
        $s = self::stripDirectives($s);

        $findings = [];

        // 1) Prose attributes. title/aria-label/alt are always prose; a
        //    placeholder is only treated as prose when it reads like a sentence
        //    (contains whitespace) — single tokens are format/example hints.
        if (preg_match_all('/\b(title|aria-label|alt|placeholder)\s*=\s*(["\'])(.*?)\2/is', $s, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $hit) {
                $attr = strtolower($hit[1]);
                $value = trim($hit[3]);
                if ($value === '' || ! preg_match('/[A-Za-z]{2,}/', $value)) {
                    continue;
                }
                if ($attr === 'placeholder' && ! preg_match('/\s/', $value)) {
                    continue;
                }
                $findings[] = $hit[1].'="'.$value.'"';
            }
        }

        // 2) Text nodes: whatever is left once the tags are gone.
        $text = preg_replace('/<[^>]*>/s', "\n", $s);
        $text = preg_replace('/&[A-Za-z]+;|&#\d+;/', ' ', $text);
        foreach (preg_split('/\n/', $text) as $line) {
            $line = trim($line);
            if ($line !== '' && preg_match('/[A-Za-z]{2,}/', $line)) {
                $findings[] = $line;
            }
        }

        return array_values(array_unique($findings));
    }

    /**
     * Translation keys referenced by a Blade template, restricted to the
     * namespaced or dotted keys that must resolve to a PHP translation file.
     * Plain JSON-style keys (e.g. __('Save')) are intentionally excluded: they
     * render via Laravel's string fallback and need no lang-file entry.
     *
     * @return list<string>
     */
    public static function referencedKeys(string $contents): array
    {
        // A key referenced only inside a Blade comment isn't really used.
        $contents = preg_replace('/\{\{--.*?--\}\}/s', ' ', $contents);

        $keys = [];
        $pattern = '/(?:trans_choice|__|@lang|trans|Lang::get)\s*\(\s*([\'"])(.*?)\1/s';
        if (preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $hit) {
                $key = $hit[2];
                if (self::isTranslationKey($key)) {
                    $keys[$key] = true;
                }
            }
        }

        return array_keys($keys);
    }

    /** Whether a string looks like a dotted/namespaced lang key (not JSON-style prose). */
    private static function isTranslationKey(string $key): bool
    {
        // Namespaced package key, e.g. "bofscheduler::admin.save".
        if (preg_match('/^[A-Za-z0-9_-]+::[A-Za-z0-9_.-]+$/', $key)) {
            return true;
        }

        // Dotted host key, e.g. "admin.save": lowercase identifier segments
        // only, no spaces. Excludes JSON-style sentences like "Step 1: ...".
        return (bool) preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z0-9_]+)+$/', $key);
    }

    /**
     * Remove Blade directives and their balanced-parenthesis argument lists,
     * e.g. "@if ($a && foo($b))", "@foreach (...)", "@checked(...)", "@csrf".
     */
    private static function stripDirectives(string $s): string
    {
        $out = '';
        $len = strlen($s);
        $i = 0;
        while ($i < $len) {
            if ($s[$i] === '@' && $i + 1 < $len && ctype_alpha($s[$i + 1])) {
                $i = self::directiveEnd($s, $i);

                continue;
            }
            $out .= $s[$i];
            $i++;
        }

        return $out;
    }

    /** The index just past the directive starting at $at: its name plus any argument list. */
    private static function directiveEnd(string $s, int $at): int
    {
        $len = strlen($s);
        $j = $at + 1;
        while ($j < $len && (ctype_alnum($s[$j]) || $s[$j] === '_')) {
            $j++;
        }

        $k = $j;
        while ($k < $len && ($s[$k] === ' ' || $s[$k] === "\t")) {
            $k++;
        }

        return ($k < $len && $s[$k] === '(') ? self::balancedEnd($s, $k) : $j;
    }

    /** The index just past the balanced parentheses opening at $open (end of string when unbalanced). */
    private static function balancedEnd(string $s, int $open): int
    {
        $depth = 0;
        $len = strlen($s);
        for ($m = $open; $m < $len; $m++) {
            if ($s[$m] === '(') {
                $depth++;
            } elseif ($s[$m] === ')') {
                $depth--;
                if ($depth === 0) {
                    return $m + 1;
                }
            }
        }

        return $len;
    }
}
