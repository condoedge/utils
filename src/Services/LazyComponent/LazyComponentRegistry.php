<?php

namespace Condoedge\Utils\Services\LazyComponent;

use Closure;
use Laravel\SerializableClosure\SerializableClosure;

class LazyComponentRegistry
{
    protected static array $counters = [];
    protected static array $pendingBatches = [];

    /**
     * Store a closure as a compiled file and return an HMAC-signed ID.
     * Uses debug_backtrace for deterministic keys based on file:line:index.
     */
    public function store(Closure $closure): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
        $caller = $trace[2] ?? $trace[1] ?? $trace[0];
        $file = $caller['file'];
        $line = $caller['line'];

        $locationKey = "{$file}:{$line}";
        static::$counters[$locationKey] = (static::$counters[$locationKey] ?? -1) + 1;
        $index = static::$counters[$locationKey];

        $rawId = sha1("{$file}:{$line}:{$index}");
        $signature = substr(hash_hmac('sha256', $rawId, config('app.key')), 0, 32);
        $signedId = "{$rawId}.{$signature}";

        $compiledPath = $this->compiledPath($signedId);
        if (!$this->isValid($compiledPath, $file)) {
            $this->compile($closure, $compiledPath);
        }

        return $signedId;
    }

    /**
     * Store a Komponent class reference (sugar syntax, no closure needed).
     */
    public function storeKomponentClass(string $komponentClass, array $store = []): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
        $caller = $trace[2] ?? $trace[1] ?? $trace[0];
        $file = $caller['file'];
        $line = $caller['line'];

        $locationKey = "{$file}:{$line}";
        static::$counters[$locationKey] = (static::$counters[$locationKey] ?? -1) + 1;
        $index = static::$counters[$locationKey];

        $rawId = sha1("{$file}:{$line}:{$index}");
        $signature = substr(hash_hmac('sha256', $rawId, config('app.key')), 0, 32);
        $signedId = "{$rawId}.{$signature}";

        $compiledPath = $this->compiledPath($signedId);
        if (!$this->isValid($compiledPath, $file)) {
            $data = serialize([
                '_type' => 'komponent',
                'class' => $komponentClass,
                'store' => $store,
            ]);

            $dir = dirname($compiledPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($compiledPath, $data);
        }

        return $signedId;
    }

    /**
     * Retrieve a stored item (closure or komponent spec), verifying the HMAC signature.
     */
    public function retrieve(string $signedId): Closure|array|null
    {
        $lastDot = strrpos($signedId, '.');
        if ($lastDot === false) return null;

        $rawId = substr($signedId, 0, $lastDot);
        $providedSig = substr($signedId, $lastDot + 1);
        $expectedSig = substr(hash_hmac('sha256', $rawId, config('app.key')), 0, 32);

        if (!hash_equals($expectedSig, $providedSig)) return null;

        $compiledPath = $this->compiledPath($signedId);
        if (!file_exists($compiledPath)) return null;

        $serialized = file_get_contents($compiledPath);
        $data = unserialize($serialized);

        if (is_array($data) && ($data['_type'] ?? null) === 'komponent') {
            return $data;
        }

        return $data->getClosure();
    }

    protected function isValid(string $compiledPath, string $sourceFile): bool
    {
        if (!file_exists($compiledPath)) return false;
        if (app()->isProduction()) return true;

        return filemtime($compiledPath) >= filemtime($sourceFile);
    }

    protected function compile(Closure $closure, string $path): void
    {
        $toSerialize = $closure;
        $unbound = @Closure::bind($closure, null, null);
        if ($unbound) {
            $toSerialize = $unbound;
        }

        $serialized = serialize(new SerializableClosure($toSerialize));

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $serialized);
    }

    protected function compiledPath(string $signedId): string
    {
        return storage_path("framework/kompo-lazy/{$signedId}.php");
    }

    // --- Batch support ---

    public static function addToBatch(string $batchId, string $lazyId, string $panelId): void
    {
        static::$pendingBatches[$batchId][] = [
            'lazyId' => $lazyId,
            'panelId' => $panelId,
        ];
    }

    public static function hasPendingBatches(): bool
    {
        return !empty(static::$pendingBatches);
    }

    public static function getBatchCoordinators(): array
    {
        $coordinators = [];

        foreach (static::$pendingBatches as $batchId => $items) {
            $batchPanelId = 'lazy-batch-' . $batchId;

            $coordinators[] = _Rows(
                _Hidden()->onLoad
                    ->post('_execute-lazy-batch', null, [
                        '_lazyItems' => json_encode($items),
                    ])
                    ->inPanel($batchPanelId),
                _Panel()->id($batchPanelId),
            )->id('lazy-batch-coord-' . $batchId)->class('hidden');
        }

        return $coordinators;
    }

    public static function resetCounters(): void
    {
        static::$counters = [];
        static::$pendingBatches = [];
    }
}
