<?php

namespace Condoedge\Utils\Services\Translation;

use Illuminate\Support\Facades\Cache;

/**
 * Owns all the filesystem I/O related to Laravel flat-JSON translation files
 * (project resource_path('lang/<locale>.json')) plus a few sibling config
 * files used by the analyzer tooling:
 *
 *   - resources/lang/<locale>.json           → canonical translation data
 *   - storage/app/translator_linked_packages.json → user-linked local packages
 *   - storage/app/translation_keys.json      → last analyzer index (cache)
 *
 * The repository exposes only pure-data methods. Display / CLI formatting
 * stays in the caller (the Command).
 */
class LocaleFilesRepository
{
    public const JSON_OPTIONS = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    private const LINKED_PACKAGES_FILE = 'translator_linked_packages.json';
    private const INDEX_CACHE_FILE     = 'translation_keys.json';
    private const INDEX_CACHE_KEY      = 'translation_keys';

    // ---------------------------------------------------------------- lang JSON

    public function load(string $locale): array
    {
        $path = resource_path("lang/{$locale}.json");
        if (!is_file($path)) {
            return [];
        }
        return json_decode(file_get_contents($path), true) ?: [];
    }

    /**
     * Overwrite a locale file entirely. Callers are responsible for passing
     * the final, already-sorted payload.
     */
    public function saveForLocale(string $locale, array $data): void
    {
        $path = resource_path("lang/{$locale}.json");
        file_put_contents(
            $path,
            json_encode($data, self::JSON_OPTIONS) . "\n"
        );
    }

    // ------------------------------------------------------------ locale config

    /**
     * Default locales from config, falling back to ['en', 'fr'].
     * @return string[]
     */
    public function defaultLocales(): array
    {
        $configured = config('app.supported_locales');
        if (is_array($configured) && !empty($configured)) {
            return $configured;
        }
        return ['en', 'fr'];
    }

    /**
     * Resolve the effective locale list: CLI override if provided, otherwise config defaults.
     * @param string[] $cliLocales Output of Console->option('locale'), may be empty.
     * @return string[]
     */
    public function resolveLocales(array $cliLocales = []): array
    {
        return !empty($cliLocales) ? $cliLocales : $this->defaultLocales();
    }

    // ----------------------------------------------------------- linked packages

    /**
     * @return string[] absolute paths the user has linked into the scan.
     */
    public function linkedPackages(): array
    {
        $path = storage_path('app/' . self::LINKED_PACKAGES_FILE);
        if (!is_file($path)) {
            return [];
        }
        $decoded = json_decode(file_get_contents($path), true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_filter($decoded, fn($p) => is_string($p) && $p !== ''));
    }

    public function saveLinkedPackages(array $paths): void
    {
        $path = storage_path('app/' . self::LINKED_PACKAGES_FILE);
        @mkdir(dirname($path), 0755, true);
        file_put_contents(
            $path,
            json_encode(array_values(array_unique($paths)), self::JSON_OPTIONS) . "\n"
        );
    }

    // ----------------------------------------------------------- scanned-keys cache

    /**
     * Persist the list of translation keys discovered by the analyzer, both in
     * the Laravel cache (for fast reuse from other code paths) and on disk for
     * debugging and the Python GUI.
     */
    public function indexKeys(array $keys): void
    {
        Cache::put(self::INDEX_CACHE_KEY, $keys, now()->addDay());
        file_put_contents(
            storage_path('app/' . self::INDEX_CACHE_FILE),
            json_encode($keys, JSON_PRETTY_PRINT) // historical format
        );
    }

    // ---------------------------------------------------------------- reports

    /**
     * @return array<string, array{empty: string[], self_ref: string[]}>
     */
    public function emptyValuesReport(array $locales): array
    {
        $report = [];
        foreach ($locales as $locale) {
            $data    = $this->load($locale);
            $empty   = [];
            $selfRef = [];
            foreach ($data as $key => $value) {
                if (!is_string($value)) {
                    continue;
                }
                $trim = trim($value);
                if ($trim === '') {
                    $empty[] = $key;
                } elseif ($trim === $key) {
                    $selfRef[] = $key;
                }
            }
            $report[$locale] = ['empty' => $empty, 'self_ref' => $selfRef];
        }
        return $report;
    }

    /**
     * For each pair of locales, list the keys present in one but not in the other.
     * @return array<string, string[]>   e.g. ['en_not_in_fr' => [...], 'fr_not_in_en' => [...]]
     */
    public function diffLocalesReport(array $locales): array
    {
        $keySets = [];
        foreach ($locales as $locale) {
            $keySets[$locale] = array_keys($this->load($locale));
        }

        $report = [];
        foreach ($locales as $locale) {
            foreach (array_diff($locales, [$locale]) as $other) {
                $diffKey = "{$locale}_not_in_{$other}";
                $report[$diffKey] = array_values(array_diff($keySets[$locale], $keySets[$other]));
            }
        }
        return $report;
    }
}
