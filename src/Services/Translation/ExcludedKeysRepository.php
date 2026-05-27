<?php

namespace Condoedge\Utils\Services\Translation;

/**
 * Persists the list of translation keys the user explicitly wants the analyzer
 * to ignore (false positives like icon names, CSS classes, etc.).
 *
 * File: storage/app/translation_exclude_keys.json — a JSON array of strings.
 * Default list seeds the file on first access.
 */
class ExcludedKeysRepository
{
    /**
     * Default seeds — common false-positive tokens that are never real
     * translation keys in a Laravel / Kompo app.
     */
    private const DEFAULT_EXCLUDED = [
        // Framework / Kompo helper names accidentally captured
        '_CollapsibleSideSection', '_CollapsibleInnerSection', '_CollapsibleSideTitle',
        '_CollapsibleSideItem', '_Button', '_Link', '_Flex', '_Html', '_Sax', '_Collapsible',
        // HTML attributes
        'class', 'id', 'href', 'src', 'alt', 'title', 'name', 'value', 'type',
        // Technical shorthand
        'php', 'js', 'css', 'html', 'json', 'xml', 'api', 'admin',
        // UI states that look like keys but aren't
        'hidden', 'active', 'disabled', 'loading', 'home', 'login', 'logout',
    ];

    public function all(): array
    {
        $path = $this->path();

        if (is_file($path)) {
            $decoded = json_decode(file_get_contents($path), true);
            if (is_array($decoded)) {
                return array_values(array_unique(array_map('strval', $decoded)));
            }
        }

        // First access — seed the file with defaults so the user can edit it later.
        $this->save(self::DEFAULT_EXCLUDED);
        return self::DEFAULT_EXCLUDED;
    }

    public function add(array $keys): int
    {
        $current = $this->all();
        $merged  = array_values(array_unique([...$current, ...array_map('strval', $keys)]));
        $this->save($merged);
        return count($merged) - count($current);
    }

    public function reset(): void
    {
        $path = $this->path();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function contains(string $key): bool
    {
        return in_array($key, $this->all(), true);
    }

    public function path(): string
    {
        return storage_path('app/translation_exclude_keys.json');
    }

    private function save(array $keys): void
    {
        @mkdir(dirname($this->path()), 0755, true);
        file_put_contents(
            $this->path(),
            json_encode(array_values($keys), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
        );
    }
}
