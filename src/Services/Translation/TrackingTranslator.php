<?php

namespace Condoedge\Utils\Services\Translation;

use Illuminate\Translation\Translator;

/**
 * Translator decorator that records every key that falls through to the
 * "key itself is the return value" branch — i.e. no translation was found
 * for the current locale.
 *
 * Misses are accumulated in memory during the request and flushed to the
 * JSON-backed {@see MissingTranslationsStore} once, in the terminating
 * kernel callback. This avoids locking + writing the store on every
 * single `__()` call (which would be catastrophic for request latency).
 *
 * Hits from a request that crashes before the terminate hook fires are
 * lost — we considered that acceptable during the DB→JSON migration.
 */
class TrackingTranslator extends Translator
{
    private ?TranslationKeyFilter $keyFilter = null;

    /**
     * Buffered misses, keyed by `<key>:<locale>` so duplicates within the
     * same request collapse into one upsert.
     *
     * @var array<string, array{key: string, locale: ?string, package: ?string, file_path: ?string}>
     */
    private array $buffer = [];

    private bool $flushRegistered = false;

    /**
     * @inheritDoc
     */
    public function get($key, array $replace = [], $locale = null, $fallback = true)
    {
        $translation = parent::get($key, $replace, $locale, $fallback);

        if ($translation === $key
            && is_string($key)
            && preg_match('/^[a-zA-Z0-9_-]+(\.[a-zA-Z0-9_-]+)+$/', $key)
            && $this->getKeyFilter()->isValidKey($key)) {
            $this->bufferMiss($key, $locale ?: $this->locale);
        }

        return $translation;
    }

    private function bufferMiss(string $key, ?string $locale): void
    {
        $bufferKey = $key . ':' . ($locale ?? '');
        if (!isset($this->buffer[$bufferKey])) {
            $this->buffer[$bufferKey] = [
                'key'       => $key,
                'locale'    => $locale,
                'package'   => $this->getPackage(),
                'file_path' => null,
            ];
            $this->registerFlushOnce();
        }
    }

    private function registerFlushOnce(): void
    {
        if ($this->flushRegistered) {
            return;
        }
        $this->flushRegistered = true;

        // Some test setups run outside a full Laravel kernel — guard both.
        $app = function_exists('app') ? app() : null;
        if ($app && method_exists($app, 'terminating')) {
            $app->terminating(function () {
                $this->flushBuffer();
            });
            return;
        }
        // Fallback: PHP shutdown hook.
        register_shutdown_function(fn() => $this->flushBuffer());
    }

    private function flushBuffer(): void
    {
        if (empty($this->buffer)) {
            return;
        }
        try {
            app(MissingTranslationsStore::class)->flushBatch(array_values($this->buffer));
        } catch (\Throwable $e) {
            // Never let telemetry crash a request.
        }
        $this->buffer = [];
    }

    private function getKeyFilter(): TranslationKeyFilter
    {
        return $this->keyFilter ??= app(TranslationKeyFilter::class);
    }

    protected function getPackage(): ?string
    {
        $backtrace = array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10), 2);
        foreach ($backtrace as $trace) {
            $class    = $trace['class']    ?? null;
            $function = $trace['function'] ?? null;
            $file     = $trace['file']     ?? null;

            if ($class && (str_starts_with($class, 'Condoedge\\') || str_starts_with($class, 'Kompo\\Auth'))) {
                return $class;
            }
            if ($function && preg_match('/^_[A-Z]/', $function) && $file) {
                return $file;
            }
        }
        return null;
    }
}
