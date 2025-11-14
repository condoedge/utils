<?php

namespace Condoedge\Utils\Services\Translation;

use Illuminate\Translation\Translator;
use Illuminate\Support\Facades\Log;
use Condoedge\Utils\Models\MissingTranslation;
use Condoedge\Utils\Services\Translation\TranslationKeyFilter;

class TrackingTranslator extends Translator
{
    /** @var TranslationKeyFilter|null */
    private $keyFilter;
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

        // Record lazily only when key looks like a namespaced translation key
        if ($translation === $key
            && is_string($key)
            && preg_match('/^[a-zA-Z0-9_-]+(\.[a-zA-Z0-9_-]+)+$/', $key)
            && $this->getKeyFilter()->isValidKey($key)) {
            try {
                MissingTranslation::upsertMissingTranslation($key, $this->getPackage());
            } catch (\Exception $e) {}
 
            return $translation;
        }
        
        return $translation;
    }

    private function getKeyFilter(): TranslationKeyFilter
    {
        if (!$this->keyFilter) {
            $this->keyFilter = new TranslationKeyFilter();
        }
        return $this->keyFilter;
    }

    protected function getPackage()
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $backtrace = array_slice($backtrace, 2); // Skip first two frames (this method and get method)

        foreach ($backtrace as $trace) {
            $class = $trace['class'] ?? null;
            $function = $trace['function'] ?? null;
            $file = $trace['file'] ?? null;

            if ($class && str_starts_with($class, 'Condoedge\\') || (str_starts_with($class, 'Kompo\\Auth'))) {
                return $class;
            }

            if ($function && preg_match('/^_[A-Z]/', $function) && $file) {
                return $file;
            }
        }
        
        return null;
    }
}
