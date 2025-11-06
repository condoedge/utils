<?php

namespace Condoedge\Utils\Models;

use Condoedge\Utils\Models\Model;
use Illuminate\Support\Facades\Cache;

class MissingTranslation extends Model
{
    public static function upsertMissingTranslation($translationKey)
    {
        $cacheKey = 'translation_missing_' . $translationKey;

        return Cache::rememberForever($cacheKey, function () use ($translationKey) {
            if ($translation = static::where('translation_key', $translationKey)->first()) {
                return $translation;
            }

            $translation = new static();
            $translation->translation_key = $translationKey;
            $translation->save();

            return $translation;
        });
    }
}