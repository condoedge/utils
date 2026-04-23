<?php

namespace Condoedge\Utils\Services\Translation;

use Condoedge\Utils\Models\MissingTranslation;
use Illuminate\Support\Facades\Lang;

class MissingTranslationChecker implements MissingTranslationCheckerInterface
{
    public function __construct(
        private readonly PhpArrayLangReader $phpArrayReader,
    ) {}

    public function has(string $key, string $locale): bool
    {
        app()->setLocale($locale);

        if (Lang::has($key, $locale)) {
            $value = Lang::get($key, [], $locale);
            if (!is_string($value)) {
                return true;
            }
            $trim = trim($value);
            if ($trim !== '' && $trim !== $key) {
                return true;
            }
            // falls through to the vendor PHP-array fallback
        }

        // Many vendor packages ship `resources/lang/<locale>/<ns>.php` but forget
        // to register them via loadTranslationsFrom(), so Lang::has() misses them.
        if (str_contains($key, '.')) {
            return $this->phpArrayReader->has($key, $locale);
        }

        return false;
    }

    public function find(array $keys, array $locales, bool $includeTriaged = false): array
    {
        [$skipGlobal, $skipPerLocale] = $this->triageSkipRules($keys, $includeTriaged);

        $missing = [];
        foreach ($locales as $locale) {
            $missing[$locale] = [];
            foreach ($keys as $key) {
                if (isset($skipGlobal[$key]) || isset($skipPerLocale[$key][$locale])) {
                    continue;
                }
                if (!$this->has($key, $locale)) {
                    $missing[$locale][] = $key;
                }
            }
        }
        return $missing;
    }

    /**
     * Load the {fixed,ignored} state from the DB so the report can skip keys
     * the user has already triaged (unless `$includeTriaged` overrides that).
     *
     * @return array{0: array<string, true>, 1: array<string, array<string, true>>}
     */
    private function triageSkipRules(array $keys, bool $includeTriaged): array
    {
        if ($includeTriaged || empty($keys)) {
            return [[], []];
        }

        $rows = MissingTranslation::query()
            ->whereIn('translation_key', $keys)
            ->where(fn($q) => $q->whereNotNull('ignored_at')->orWhereNotNull('fixed_at'))
            ->get(['translation_key', 'locale', 'ignored_at', 'fixed_at']);

        $global    = [];
        $perLocale = [];
        foreach ($rows as $row) {
            if (empty($row->locale)) {
                $global[$row->translation_key] = true;
            } else {
                $perLocale[$row->translation_key][$row->locale] = true;
            }
        }
        return [$global, $perLocale];
    }
}
