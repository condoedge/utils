<?php

namespace Condoedge\Utils\Models;

use Condoedge\Utils\Models\Model;
use Illuminate\Support\Facades\Cache;

class MissingTranslation extends Model
{
    protected static function booted()
    {
        parent::booted();

        static::saved(function (self $model) {
            if ($model->wasChanged('fixed_at') || $model->wasChanged('ignored_at')) {
                $model->clearDetectionCache();
            }
        });

        static::deleted(function (self $model) {
            $model->clearDetectionCache();
        });
    }

    public function clearDetectionCache(): void
    {
        $key = $this->translation_key;
        if (!$key) {
            return;
        }

        // Clear both the per-locale cache entry and the legacy wildcard entry used before locale split.
        Cache::forget('translation_missing_' . $key . ':' . ($this->locale ?? '*'));
        Cache::forget('translation_missing_' . $key . ':*');
        Cache::forget('translation_missing_' . $key);
    }

    public static function upsertMissingTranslation($translationKey, $package = null, ?string $locale = null, ?string $filePath = null)
    {
        $cacheKey = 'translation_missing_' . $translationKey . ':' . ($locale ?? '*');

        if ($cached = Cache::get($cacheKey)) {
            $cached->increment('hit_count');
            $cached->last_seen_at = now();
            $cached->save();
            return $cached;
        }

        $translation = static::where('translation_key', $translationKey)
            ->where(function ($q) use ($locale) {
                $locale === null ? $q->whereNull('locale') : $q->where('locale', $locale);
            })
            ->first();

        if (!$translation) {
            $translation = new static();
            $translation->translation_key = $translationKey;
            $translation->locale = $locale;
            $translation->hit_count = 0;
        }

        $translation->package = $package ?: $translation->package;
        $translation->file_path = $filePath ?: $translation->file_path;
        $translation->hit_count = ($translation->hit_count ?? 0) + 1;
        $translation->last_seen_at = now();
        $translation->save();

        Cache::put($cacheKey, $translation, now()->addDay());

        return $translation;
    }

    public function scopeUnresolved($query)
    {
        return $query->whereNull('fixed_at')->whereNull('ignored_at');
    }

    public function scopeForLocale($query, ?string $locale)
    {
        return $query->where('locale', $locale);
    }
}