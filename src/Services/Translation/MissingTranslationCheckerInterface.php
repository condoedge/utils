<?php

namespace Condoedge\Utils\Services\Translation;

/**
 * Pure logic for deciding which translation keys are missing in a given
 * locale. Implementations must be stateless and side-effect-free (no DB
 * writes, no logging) — persistence and output formatting are caller concerns.
 */
interface MissingTranslationCheckerInterface
{
    /**
     * True when `$key` has a non-empty, non-self-referencing translation
     * available to Laravel in `$locale` (directly via `Lang::has()` or via
     * a vendor package's PHP-array lang file).
     */
    public function has(string $key, string $locale): bool;

    /**
     * Scan `$keys` against every `$locale` and return the ones without a
     * valid translation, keyed by locale.
     *
     * @param string[] $keys
     * @param string[] $locales
     * @return array<string, string[]>  e.g. ['en' => ['foo','bar'], 'fr' => ['baz']]
     */
    public function find(array $keys, array $locales, bool $includeTriaged = false): array;
}
