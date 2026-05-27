<?php

namespace Condoedge\Utils\Services\Translation;

/**
 * Reads Laravel-style PHP array translation files from vendor packages.
 *
 * Many packages ship `resources/lang/<locale>/<namespace>.php` files that return
 * a PHP array, but forget to call `loadTranslationsFrom()` in their service
 * provider. The framework then can't find those keys via `Lang::has()`.
 *
 * This reader scans such files directly so the analyzer can still tell the user
 * "the key exists, it's just not registered".
 *
 * Key shape expected: `<namespace>.<array_key>` (e.g. `cms.add-zone`).
 * Nested array keys are supported: `cms.a.b.c` → namespace `cms`, chain `a.b.c`.
 */
class PhpArrayLangReader
{
    /** @var array<string, array>  path => loaded array (per-request cache) */
    private array $cache = [];

    /**
     * @return string[] list of scanned roots (vendor package roots with lang dirs)
     */
    public function discoverRoots(array $vendors = ['condoedge', 'kompo']): array
    {
        $roots = [];
        foreach ($vendors as $v) {
            $dir = base_path('vendor/' . $v);
            if (!is_dir($dir)) {
                continue;
            }
            foreach (glob($dir . '/*', GLOB_ONLYDIR) ?: [] as $pkg) {
                $langDir = $pkg . '/resources/lang';
                if (is_dir($langDir)) {
                    $roots[] = $pkg;
                }
            }
        }
        return $roots;
    }

    /**
     * Check whether `$key` (form `namespace.rest`) has a non-empty translation
     * in any vendor PHP-array lang file for the given locale.
     */
    public function has(string $key, string $locale, ?array $roots = null): bool
    {
        $value = $this->get($key, $locale, $roots);
        if (!is_string($value)) {
            return $value !== null;  // array / nested group counts as present
        }
        $trim = trim($value);
        return $trim !== '' && $trim !== $key;
    }

    /**
     * Retrieve the translation value (string, array, or null if not found).
     */
    public function get(string $key, string $locale, ?array $roots = null)
    {
        if (!str_contains($key, '.')) {
            return null;
        }
        [$namespace, $rest] = explode('.', $key, 2);

        foreach ($roots ?? $this->discoverRoots() as $pkg) {
            $file = $pkg . '/resources/lang/' . $locale . '/' . $namespace . '.php';
            $data = $this->load($file);
            if ($data === null) {
                continue;
            }
            $value = $this->extractNested($data, $rest);
            if ($value !== null) {
                return $value;
            }
        }
        return null;
    }

    /**
     * Flatten every PHP-array lang file under the given package into
     * [ "<namespace>.<nested.key>" => "value", ... ].
     */
    public function flattenPackage(string $pkgPath, string $locale): array
    {
        $out = [];
        $dir = $pkgPath . '/resources/lang/' . $locale;
        if (!is_dir($dir)) {
            return $out;
        }
        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $namespace = pathinfo($file, PATHINFO_FILENAME);
            $data = $this->load($file);
            if (!is_array($data)) {
                continue;
            }
            $this->flattenInto($data, $namespace, $out);
        }
        return $out;
    }

    private function load(string $file): ?array
    {
        if (!is_file($file)) {
            return null;
        }
        if (!array_key_exists($file, $this->cache)) {
            try {
                $data = include $file;
                $this->cache[$file] = is_array($data) ? $data : null;
            } catch (\Throwable $e) {
                $this->cache[$file] = null;
            }
        }
        return $this->cache[$file];
    }

    private function extractNested(array $data, string $chain)
    {
        $current = $data;
        foreach (explode('.', $chain) as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }
        return $current;
    }

    private function flattenInto(array $data, string $prefix, array &$out): void
    {
        foreach ($data as $k => $v) {
            $full = $prefix . '.' . $k;
            if (is_array($v)) {
                $this->flattenInto($v, $full, $out);
            } else {
                $out[$full] = $v;
            }
        }
    }
}
