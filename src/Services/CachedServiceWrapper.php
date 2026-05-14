<?php

namespace Condoedge\Utils\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Lightweight decorator that wraps any service instance and routes its method
 * calls through Laravel's Cache facade with a TTL. The wrapper has two modes:
 *
 *  - Per-call:   ->cache($explicitKey, $ttl)->method(...)
 *  - Blanket:    ->cacheFor($ttl)->method(...)   (auto-key = class:method:hash(args))
 *
 * The kill switch `kompo-utils.cache_wrapping_enabled` (false by default) lets
 * tests and local environments bypass the wrapper without changing call sites.
 *
 * Best fit: read-only services whose methods all share the same TTL semantics
 * (e.g. report aggregation services). For complex tag-based invalidation, use
 * a typed Interface + Cached* decorator (auth package pattern) instead.
 */
class CachedServiceWrapper
{
    protected ?string $cacheKey = null;
    protected ?int $cacheTtl = null;
    protected ?int $generalCacheTtl = null;

    public function __construct(
        protected object $service
    ) {
    }

    public static function for(object|string $service): static
    {
        if (is_string($service)) {
            $service = app($service);
        }

        return new static($service);
    }

    public function cache(string $key, int $ttl): self
    {
        $this->cacheKey = $key;
        $this->cacheTtl = $ttl;
        return $this;
    }

    public function cacheFor(int $ttl): self
    {
        $this->generalCacheTtl = $ttl;
        return $this;
    }

    protected function executeWithCache(string $method, array $args)
    {
        if (!config('kompo-utils.cache_wrapping_enabled', false)) {
            return $this->service->$method(...$args);
        }

        $cacheKey = $this->cacheKey;
        $cacheTtl = $this->cacheTtl;

        $this->cacheKey = null;
        $this->cacheTtl = null;

        if ($cacheKey !== null && $cacheTtl !== null) {
            return Cache::remember($cacheKey, $cacheTtl, function () use ($method, $args) {
                return $this->service->$method(...$args);
            });
        }

        if ($this->generalCacheTtl !== null) {
            $generalCacheKey = get_class($this->service) . ':' . $method . ':' . md5(json_encode($args));

            return Cache::remember($generalCacheKey, $this->generalCacheTtl, function () use ($method, $args) {
                return $this->service->$method(...$args);
            });
        }

        return $this->service->$method(...$args);
    }

    public function __call(string $method, array $args)
    {
        return $this->executeWithCache($method, $args);
    }

    public function getService(): object
    {
        return $this->service;
    }

    public function clearCache(string $cacheKey): bool
    {
        return Cache::forget($cacheKey);
    }
}
