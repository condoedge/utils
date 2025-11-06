<?php

namespace Condoedge\Utils\Services\Translation;

use Illuminate\Translation\Translator;
use Illuminate\Support\Facades\Log;
use Condoedge\Utils\Models\MissingTranslation;

class TrackingTranslator extends Translator
{
    /**
     * Get the translation for the given key.
     *
     * @param  string  $key
     * @param  array  $replace
     * @param  string|null  $locale
     * @param  bool  $fallback
     * @return string|array
     */
    public function get($key, array $replace = [], $locale = null, $fallback = true)
    {
        $translation = parent::get($key, $replace, $locale, $fallback);

        if ($translation === $key && is_string($key) && preg_match('/^[a-zA-Z0-9_-]+(\.[a-zA-Z0-9_-]+)$/', $key)) {
            try {
                MissingTranslation::upsertMissingTranslation($key);
            } catch (\Exception $e) {}
 
            return $translation;
        }
        
        return $translation;
    }
}
