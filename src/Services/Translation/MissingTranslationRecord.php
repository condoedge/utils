<?php

namespace Condoedge\Utils\Services\Translation;

/**
 * Immutable record representing a single (translation_key, locale) entry in
 * the JSON-backed {@see MissingTranslationsStore}. Replaces the old Eloquent
 * model used before the DB was removed.
 *
 * The `id` is a deterministic hash of `<key>:<locale>` so Kompo UI actions
 * (markAsFixed/markAsIgnored) can address rows without a DB auto-increment.
 */
class MissingTranslationRecord
{
    public readonly string $id;

    public function __construct(
        public readonly string $translation_key,
        public readonly ?string $locale,
        public readonly int $hit_count = 0,
        public readonly ?string $last_seen_at = null,
        public readonly ?string $fixed_at = null,
        public readonly ?string $ignored_at = null,
        public readonly ?string $package = null,
        public readonly ?string $file_path = null,
    ) {
        $this->id = self::makeId($translation_key, $locale);
    }

    public static function makeId(string $translationKey, ?string $locale): string
    {
        return md5($translationKey . ':' . ($locale ?? ''));
    }

    /**
     * @param array{
     *   translation_key: string,
     *   locale?: ?string,
     *   hit_count?: int,
     *   last_seen_at?: ?string,
     *   fixed_at?: ?string,
     *   ignored_at?: ?string,
     *   package?: ?string,
     *   file_path?: ?string
     * } $data
     */
    public static function fromArray(string $key, ?string $locale, array $data): self
    {
        return new self(
            translation_key: $key,
            locale: $locale,
            hit_count: (int) ($data['hit_count'] ?? 0),
            last_seen_at: $data['last_seen_at'] ?? null,
            fixed_at: $data['fixed_at'] ?? null,
            ignored_at: $data['ignored_at'] ?? null,
            package: $data['package'] ?? null,
            file_path: $data['file_path'] ?? null,
        );
    }

    /**
     * Shape used inside the JSON file (under `data[key][locale_or_empty]`).
     *
     * @return array{hit_count: int, last_seen_at: ?string, fixed_at: ?string, ignored_at: ?string, package: ?string, file_path: ?string}
     */
    public function toStorageArray(): array
    {
        return [
            'hit_count'    => $this->hit_count,
            'last_seen_at' => $this->last_seen_at,
            'fixed_at'     => $this->fixed_at,
            'ignored_at'   => $this->ignored_at,
            'package'      => $this->package,
            'file_path'    => $this->file_path,
        ];
    }
}
