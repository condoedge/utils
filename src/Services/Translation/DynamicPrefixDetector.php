<?php

namespace Condoedge\Utils\Services\Translation;

/**
 * Collects literal namespace prefixes used in DYNAMIC translation calls.
 *
 * A dynamic call is any translation helper invocation whose first argument
 * contains a variable (interpolation or concatenation). The detector extracts
 * the literal head that precedes the variable — e.g. in a call like
 * `trans(<ns-dot-variable>)` the head "ns." is remembered so that later,
 * any translation key starting with "ns." can be considered "used
 * dynamically" even though no static string match exists for it.
 *
 * NOTE: examples are intentionally written with placeholders rather than
 * actual quoted strings, to avoid this file self-matching its own regexes
 * when the analyzer scans the vendor directory.
 *
 * Usage:
 *   $d = new DynamicPrefixDetector($translationFunctions);
 *   foreach ($files as $content) { $d->collect($content); }
 *   $d->matches('events.foo'); // true if any prefix matches
 *   $d->prefixes();            // list of detected prefixes for reporting
 *
 * The list of translation function regex fragments comes from the command's
 * TRANSLATION_FUNCTIONS metadata so both literal and dynamic detection stay
 * in sync (single source of truth).
 */
class DynamicPrefixDetector
{
    /** @var array<string, true> set of detected prefixes (with trailing dot) */
    private array $prefixes = [];

    /** @var string[] compiled regex patterns built once */
    private array $patterns;

    /**
     * @param array<int, array{regex:string, lookbehind?:string, is_raw_prefix?:bool}> $translationFunctions
     */
    public function __construct(array $translationFunctions)
    {
        $this->patterns = $this->buildPatterns($translationFunctions);
    }

    /**
     * Scan a file's content and remember every static prefix used in dynamic
     * translation calls.
     */
    public function collect(string $content): void
    {
        foreach ($this->patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $prefix) {
                    $this->prefixes[$prefix] = true;
                }
            }
        }
    }

    public function matches(string $key): bool
    {
        foreach (array_keys($this->prefixes) as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return string[] sorted list of detected prefixes
     */
    public function prefixes(): array
    {
        $list = array_keys($this->prefixes);
        sort($list);
        return $list;
    }

    public function count(): int
    {
        return count($this->prefixes);
    }

    /**
     * Build one regex per translation function that captures the static head
     * of a dynamic call (interpolation or concatenation form).
     *
     * @param array<int, array{regex:string, lookbehind?:string, is_raw_prefix?:bool}> $functions
     * @return string[]
     */
    private function buildPatterns(array $functions): array
    {
        $patterns = [];
        foreach ($functions as $fn) {
            if (!empty($fn['is_raw_prefix'])) {
                continue;
            }
            $head = ($fn['lookbehind'] ?? '') . $fn['regex'];

            // Double-quoted interpolation form: static head before $ or {$
            $patterns[] = '/' . $head . '\s*\(\s*["]([a-zA-Z][a-zA-Z0-9._-]*\.)[^"]*(?:\$|\{\$)/u';

            // Concatenation form: static head followed by dot-concat with a variable
            $patterns[] = '/' . $head . '\s*\(\s*[\'"]([a-zA-Z][a-zA-Z0-9._-]*\.)[\'"]\s*\./u';
        }
        return $patterns;
    }
}
