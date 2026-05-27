<?php

namespace Condoedge\Utils\Services\Translation;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

/**
 * Minimal chainable wrapper around a {@see Collection} of
 * {@see MissingTranslationRecord} that mimics the subset of Eloquent's query
 * builder actually used by the rewritten Kompo table and the mark/email
 * commands. Built on Laravel's Collection — no database involved.
 */
class MissingTranslationsQuery
{
    /**
     * @param Collection<int, MissingTranslationRecord> $records  current filtered set
     * @param Collection<int, MissingTranslationRecord>|null $original  set `where()` closures fall back to for OR clauses
     */
    public function __construct(
        private Collection $records,
        private ?Collection $original = null,
    ) {
        $this->original ??= $records;
    }

    // ---------------------------------------------------------------- filters

    public function when(bool $condition, \Closure $callback): self
    {
        return $condition ? ($callback($this) ?? $this) : $this;
    }

    public function where(\Closure|string $fieldOrClosure, mixed $value = null): self
    {
        if ($fieldOrClosure instanceof \Closure) {
            // Closure = AND-group: evaluate sub-query scoped to the CURRENT records,
            // then intersect by id so OR clauses inside only look up rows this
            // outer query already kept.
            $nested = $fieldOrClosure(new self($this->records, $this->records));
            if ($nested instanceof self) {
                $ids = $nested->records->pluck('id')->all();
                return new self(
                    $this->records->filter(fn($r) => in_array($r->id, $ids, true))->values(),
                    $this->original,
                );
            }
            return $this;
        }
        return new self(
            $this->records->filter(fn($r) => $this->field($r, $fieldOrClosure) === $value)->values(),
            $this->original,
        );
    }

    public function whereIn(string $field, array $values): self
    {
        return new self(
            $this->records->filter(fn($r) => in_array($this->field($r, $field), $values, true))->values(),
            $this->original,
        );
    }

    public function whereNull(string $field): self
    {
        return new self(
            $this->records->filter(fn($r) => $this->field($r, $field) === null)->values(),
            $this->original,
        );
    }

    public function whereNotNull(string $field): self
    {
        return new self(
            $this->records->filter(fn($r) => $this->field($r, $field) !== null)->values(),
            $this->original,
        );
    }

    /**
     * Union with the original (pre-filter) baseline: keep rows where $field is
     * NOT NULL, in addition to whatever the current chain already matched.
     */
    public function orWhereNotNull(string $field): self
    {
        $extra = $this->original->filter(fn($r) => $this->field($r, $field) !== null);
        $merged = $this->records->merge($extra)->unique(fn($r) => $r->id)->values();
        return new self($merged, $this->original);
    }

    // ---------------------------------------------------------------- terminal

    /**
     * The $columns argument is accepted for Eloquent API parity but ignored —
     * records are in-memory DTOs, we don't project columns.
     *
     * @return Collection<int, MissingTranslationRecord>
     */
    public function get(array $columns = []): Collection
    {
        return $this->records;
    }

    public function first(): ?MissingTranslationRecord
    {
        return $this->records->first();
    }

    public function count(): int
    {
        return $this->records->count();
    }

    public function paginate(int $perPage = 15, ?int $page = null): LengthAwarePaginator
    {
        $page ??= Paginator::resolveCurrentPage();
        $total = $this->records->count();
        $items = $this->records->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    // ---------------------------------------------------------------- internal

    private function field(MissingTranslationRecord $record, string $name): mixed
    {
        return match ($name) {
            'id'              => $record->id,
            'translation_key' => $record->translation_key,
            'locale'          => $record->locale,
            'hit_count'       => $record->hit_count,
            'last_seen_at'    => $record->last_seen_at,
            'fixed_at'        => $record->fixed_at,
            'ignored_at'      => $record->ignored_at,
            'package'         => $record->package,
            'file_path'       => $record->file_path,
            default           => null,
        };
    }
}
