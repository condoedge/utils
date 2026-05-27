<?php

namespace Condoedge\Utils\Models;

use Condoedge\Utils\Services\Translation\MissingTranslationRecord;
use Condoedge\Utils\Services\Translation\MissingTranslationsQuery;
use Condoedge\Utils\Services\Translation\MissingTranslationsStore;

/**
 * Façade over the JSON-backed {@see MissingTranslationsStore}. Kept in the
 * Models namespace (and with the same class name the rest of the codebase
 * was using) so existing callers — TrackingTranslator, the CLI commands,
 * the Kompo table — keep working after the DB was removed.
 *
 * This class no longer extends Eloquent's `Model` — there is no database row
 * behind it. Instances are thin wrappers around a read-only
 * {@see MissingTranslationRecord} DTO.
 */
class MissingTranslation
{
    public function __construct(public readonly MissingTranslationRecord $record) {}

    // -------------------------------------------------------------- BC API

    /**
     * Same signature the rest of the codebase was calling before the DB
     * was replaced. Returns the upserted row (as a façade) so callers that
     * do `->increment('hit_count')` or read properties keep working.
     */
    public static function upsertMissingTranslation(
        string $translationKey,
        $package = null,
        ?string $locale = null,
        ?string $filePath = null,
    ): self {
        $record = static::store()->upsert($translationKey, $locale, $package, $filePath);
        return new self($record);
    }

    public static function query(): MissingTranslationsQuery
    {
        return static::store()->query();
    }

    public static function findOrFail(string $id): self
    {
        $record = static::store()->findById($id);
        if (!$record) {
            throw new \RuntimeException("Missing translation [{$id}] not found.");
        }
        return new self($record);
    }

    public static function unresolved(): MissingTranslationsQuery
    {
        return static::store()->query()
            ->whereNull('fixed_at')
            ->whereNull('ignored_at');
    }

    // -------------------------------------------------------------- instance

    /**
     * Proxy reads to the underlying record so callers can keep using
     * `$row->translation_key`, `$row->hit_count`, etc.
     */
    public function __get(string $name): mixed
    {
        return $this->record->{$name} ?? null;
    }

    public function markFixed(): self
    {
        return new self(static::store()->markFixed($this->record->id) ?? $this->record);
    }

    public function markIgnored(): self
    {
        return new self(static::store()->markIgnored($this->record->id) ?? $this->record);
    }

    public function reset(): self
    {
        return new self(static::store()->reset($this->record->id) ?? $this->record);
    }

    public function delete(): bool
    {
        return static::store()->delete($this->record->id);
    }

    // -------------------------------------------------------------- helpers

    private static function store(): MissingTranslationsStore
    {
        return app(MissingTranslationsStore::class);
    }
}
