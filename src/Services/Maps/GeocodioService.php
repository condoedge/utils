<?php

namespace Condoedge\Utils\Services\Maps;

use Condoedge\Utils\Services\AbstractApiClientService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodioService extends AbstractApiClientService implements GeocodingBatchService
{
    protected $addressesCache = [];
    protected $qtyLimitPerDay = 2500;

    protected function getBaseUrl()
    {
        return config('services.geocodio.base_url');
    }

    public function geocode(string|array $address): ?GeocodingResult
    {
        if (is_array($address)) {
            $address = implode(', ', $address);
        }

        if (isset($this->addressesCache[$address])) {
            return $this->addressesCache[$address];
        }

        if (!$this->canMakeRequest()) {
            Log::warning("Geocodio API request limit reached for the month. Cannot geocode address: {$address}");

            return null;
        }

        $response = $this->request('get', 'geocode', [
            'q' => $address,
            'api_key' => $this->apiKey,
        ]);

        $this->incrementUsage();

        if (isset($response['results'][0])) {
            $location = $response['results'][0]['location'];

            $result = new GeocodingResult($location['lat'], $location['lng']);
            $this->addressesCache[$address] = $result;

            return $result;
        }

        return null;
    }

    public function geocodeBatch(array $addresses): array
    {
        $results = Http::withQueryParameters([
            'api_key' => $this->apiKey,
            'limit' => count($addresses),
        ])->post($this->getBaseUrl() . '/geocode', $addresses)->json();

        $results = collect($results['results'] ?? [])->mapWithKeys(function ($item) {
            $location = $item["response"]['results'][0]['location'] ?? null;

            if ($location) {
                return [
                    $item['query'] => new GeocodingResult($location['lat'], $location['lng']),
                ];
            }

            return [];
        })->filter()->toArray();

        $this->incrementUsage(count($addresses));

        return $results;
    }

    public function acceptsBatch(): bool
    {
        return true;
    }

    protected function getUsageKey(): string
    {
        return 'geocodio_api_usage_' . date('Y_m_d');
    }

    protected function getUsage(): int
    {
        $cacheKey = $this->getUsageKey();

        return cache()->get($cacheKey, 0);
    }

    protected function incrementUsage($by = 1): void
    {
        $cacheKey = $this->getUsageKey();
        cache()->increment($cacheKey, $by);
    }

    protected function canMakeRequest($qty = 1): bool
    {
        return ($this->getUsage() + $qty) <= $this->qtyLimitPerDay;
    }
}