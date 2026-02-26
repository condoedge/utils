<?php

namespace Condoedge\Utils\Models\Traits;

trait MemoizesResults
{
    protected static array $_memoizedStatic = [];

    protected function memoize(string $key, callable $callback)
    {
        $instanceKey = static::class . ':' . ($this->getKey() ?? spl_object_id($this));
        $fullKey = $instanceKey . ':' . $key;

        if (!array_key_exists($fullKey, static::$_memoizedStatic)) {
            static::$_memoizedStatic[$fullKey] = $callback();
        }

        return static::$_memoizedStatic[$fullKey];
    }

    public static function classMemoize(string $key, callable $callback)
    {
        $fullKey = static::class . ':class:' . $key;

        if (!array_key_exists($fullKey, static::$_memoizedStatic)) {
            static::$_memoizedStatic[$fullKey] = $callback();
        }

        return static::$_memoizedStatic[$fullKey];
    }

    public function preMemoize(string $key, $value)
    {
        $instanceKey = static::class . ':' . ($this->getKey() ?? spl_object_id($this));
        static::$_memoizedStatic[$instanceKey . ':' . $key] = $value;
    }

    public static function preMemoizeForKey($modelKey, string $memoKey, $value)
    {
        $instanceKey = static::class . ':' . $modelKey;
        static::$_memoizedStatic[$instanceKey . ':' . $memoKey] = $value;
    }

    public function scopeWithMemoized($query, string $memoKey, callable $batchLoader)
    {
        return $query->afterQuery(function ($models) use ($memoKey, $batchLoader) {
            if ($models->isEmpty()) {
                return $models;
            }

            $results = $batchLoader($models);

            foreach ($models as $model) {
                $model->preMemoize($memoKey, $results[$model->getKey()] ?? null);
            }

            return $models;
        });
    }
}
