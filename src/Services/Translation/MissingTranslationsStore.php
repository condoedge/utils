<?php

namespace Condoedge\Utils\Services\Translation;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * JSON-backed persistence layer for missing-translation tracking. Replaces
 * the old Eloquent {@see \Condoedge\Utils\Models\MissingTranslation} table.
 *
 * File layout (storage/app/missing_translations.json):
 *
 *   {
 *     "events.save": {
 *       "fr": { "hit_count": 5, "last_seen_at": "...", "fixed_at": null, "ignored_at": null, "package": "...", "file_path": "..." },
 *       "en": { ... }
 *     },
 *     ...
 *   }
 *
 * A row with `locale === null` is stored under the empty-string key `""`.
 *
 * Concurrency: all writes go through {@see mutate()} which holds an exclusive
 * flock on the JSON file and swaps the new contents via atomic rename. Reads
 * are cached in the Laravel cache for 1 day per (key, locale) pair.
 */
class MissingTranslationsStore
{
    public const JSON_OPTIONS = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    private const STORE_FILE = 'missing_translations.json';
    private const CACHE_PREFIX = 'translation_missing_';

    // --------------------------------------------------------------- path
    public function path(): string
    {
        return storage_path('app/' . self::STORE_FILE);
    }

    // --------------------------------------------------------------- read
    /**
     * Whole store as a flat collection, one entry per (key, locale).
     * @return Collection<int, MissingTranslationRecord>
     */
    public function all(): Collection
    {
        $data = $this->load();
        $records = [];
        foreach ($data as $key => $perLocale) {
            if (!is_array($perLocale)) {
                continue;
            }
            foreach ($perLocale as $localeKey => $payload) {
                $records[] = MissingTranslationRecord::fromArray(
                    $key,
                    $localeKey === '' ? null : $localeKey,
                    is_array($payload) ? $payload : [],
                );
            }
        }
        return collect($records);
    }

    public function find(string $key, ?string $locale): ?MissingTranslationRecord
    {
        $cacheKey = self::CACHE_PREFIX . $key . ':' . ($locale ?? '*');
        $cached = Cache::get($cacheKey);
        if ($cached instanceof MissingTranslationRecord) {
            return $cached;
        }

        $data = $this->load();
        $localeKey = $locale ?? '';
        if (!isset($data[$key][$localeKey])) {
            return null;
        }

        $record = MissingTranslationRecord::fromArray($key, $locale, $data[$key][$localeKey]);
        Cache::put($cacheKey, $record, now()->addDay());
        return $record;
    }

    public function findById(string $id): ?MissingTranslationRecord
    {
        return $this->all()->first(fn(MissingTranslationRecord $r) => $r->id === $id);
    }

    /**
     * @return Collection<int, MissingTranslationRecord>
     */
    public function unresolved(?string $locale = null): Collection
    {
        return $this->all()
            ->filter(fn(MissingTranslationRecord $r) => $r->fixed_at === null && $r->ignored_at === null)
            ->when($locale !== null, fn($c) => $c->filter(fn($r) => $r->locale === $locale))
            ->values();
    }

    public function query(): MissingTranslationsQuery
    {
        return new MissingTranslationsQuery($this->all());
    }

    // --------------------------------------------------------------- write
    /**
     * Upsert a single entry, incrementing hit_count and refreshing last_seen_at.
     */
    public function upsert(
        string $key,
        ?string $locale,
        ?string $package = null,
        ?string $filePath = null,
    ): MissingTranslationRecord {
        return $this->mutate(function (array &$data) use ($key, $locale, $package, $filePath) {
            $localeKey = $locale ?? '';
            $entry = $data[$key][$localeKey] ?? [];

            $entry['hit_count']    = ((int) ($entry['hit_count'] ?? 0)) + 1;
            $entry['last_seen_at'] = now()->toIso8601String();
            $entry['fixed_at']     = $entry['fixed_at']   ?? null;
            $entry['ignored_at']   = $entry['ignored_at'] ?? null;
            $entry['package']      = $package  ?: ($entry['package']   ?? null);
            $entry['file_path']    = $filePath ?: ($entry['file_path'] ?? null);

            $data[$key][$localeKey] = $entry;

            $record = MissingTranslationRecord::fromArray($key, $locale, $entry);
            $this->bustCache($key, $locale);
            Cache::put(self::CACHE_PREFIX . $key . ':' . ($locale ?? '*'), $record, now()->addDay());
            return $record;
        });
    }

    /**
     * Bulk upsert used by {@see TrackingTranslator} so every request only
     * pays one flock + read + write for all the misses it accumulated.
     *
     * @param array<int, array{key: string, locale: ?string, package?: ?string, file_path?: ?string}> $hits
     */
    public function flushBatch(array $hits): void
    {
        if (empty($hits)) {
            return;
        }
        $this->mutate(function (array &$data) use ($hits) {
            $now = now()->toIso8601String();
            foreach ($hits as $hit) {
                $key       = $hit['key'];
                $locale    = $hit['locale'] ?? null;
                $localeKey = $locale ?? '';
                $entry     = $data[$key][$localeKey] ?? [];

                $entry['hit_count']    = ((int) ($entry['hit_count'] ?? 0)) + 1;
                $entry['last_seen_at'] = $now;
                $entry['fixed_at']     = $entry['fixed_at']   ?? null;
                $entry['ignored_at']   = $entry['ignored_at'] ?? null;
                $entry['package']      = ($hit['package']   ?? null) ?: ($entry['package']   ?? null);
                $entry['file_path']    = ($hit['file_path'] ?? null) ?: ($entry['file_path'] ?? null);

                $data[$key][$localeKey] = $entry;
                $this->bustCache($key, $locale);
            }
            return null;
        });
    }

    public function markFixed(string $id): ?MissingTranslationRecord
    {
        return $this->stamp($id, 'fixed_at', now()->toIso8601String());
    }

    public function markIgnored(string $id): ?MissingTranslationRecord
    {
        return $this->stamp($id, 'ignored_at', now()->toIso8601String());
    }

    public function reset(string $id): ?MissingTranslationRecord
    {
        return $this->mutate(function (array &$data) use ($id) {
            foreach ($data as $key => &$perLocale) {
                foreach ($perLocale as $localeKey => &$entry) {
                    $locale = $localeKey === '' ? null : $localeKey;
                    if (MissingTranslationRecord::makeId($key, $locale) !== $id) {
                        continue;
                    }
                    $entry['fixed_at']   = null;
                    $entry['ignored_at'] = null;
                    $this->bustCache($key, $locale);
                    return MissingTranslationRecord::fromArray($key, $locale, $entry);
                }
            }
            return null;
        });
    }

    public function delete(string $id): bool
    {
        return (bool) $this->mutate(function (array &$data) use ($id) {
            foreach ($data as $key => $perLocale) {
                foreach ($perLocale as $localeKey => $_) {
                    $locale = $localeKey === '' ? null : $localeKey;
                    if (MissingTranslationRecord::makeId($key, $locale) !== $id) {
                        continue;
                    }
                    unset($data[$key][$localeKey]);
                    if (empty($data[$key])) {
                        unset($data[$key]);
                    }
                    $this->bustCache($key, $locale);
                    return true;
                }
            }
            return false;
        });
    }

    // --------------------------------------------------------------- internals

    /**
     * Tag + overwrite helper.
     */
    private function stamp(string $id, string $field, string $when): ?MissingTranslationRecord
    {
        return $this->mutate(function (array &$data) use ($id, $field, $when) {
            foreach ($data as $key => &$perLocale) {
                foreach ($perLocale as $localeKey => &$entry) {
                    $locale = $localeKey === '' ? null : $localeKey;
                    if (MissingTranslationRecord::makeId($key, $locale) !== $id) {
                        continue;
                    }
                    $entry[$field] = $when;
                    $this->bustCache($key, $locale);
                    return MissingTranslationRecord::fromArray($key, $locale, $entry);
                }
            }
            return null;
        });
    }

    /**
     * Exclusive file-locked read-modify-write, with atomic rename on save.
     * $callback receives the current data by reference and returns an arbitrary
     * value that is bubbled back to the caller.
     *
     * @template T
     * @param callable(array &$data): T $callback
     * @return T
     */
    private function mutate(callable $callback)
    {
        $path = $this->path();
        @mkdir(dirname($path), 0755, true);

        // Ensure the file exists so flock can lock it.
        if (!is_file($path)) {
            file_put_contents($path, "{}\n");
        }

        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open missing_translations store at {$path}");
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Cannot acquire exclusive lock on missing_translations store');
            }
            rewind($handle);
            $raw     = stream_get_contents($handle) ?: '{}';
            $decoded = json_decode($raw, true);
            $data    = is_array($decoded) ? $decoded : [];

            $result = $callback($data);

            // Rewrite through the same locked handle — the classic rename-over
            // atomic swap fails on Windows when the target is still flock-held.
            $payload = json_encode($data, self::JSON_OPTIONS) . "\n";
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, $payload);
            fflush($handle);
            return $result;
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }

    private function load(): array
    {
        $path = $this->path();
        if (!is_file($path)) {
            return [];
        }
        $decoded = json_decode(file_get_contents($path) ?: '[]', true);
        return is_array($decoded) ? $decoded : [];
    }

    private function bustCache(string $key, ?string $locale): void
    {
        Cache::forget(self::CACHE_PREFIX . $key . ':' . ($locale ?? '*'));
        Cache::forget(self::CACHE_PREFIX . $key . ':*');
        Cache::forget(self::CACHE_PREFIX . $key);
    }
}
