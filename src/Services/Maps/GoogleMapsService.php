<?php

namespace Condoedge\Utils\Services\Maps;

use Condoedge\Utils\Services\AbstractApiClientService;
use Illuminate\Support\Facades\Log;

class GoogleMapsService extends AbstractApiClientService implements GeocodingService
{
    protected $addressesCache = [];
    protected $qtyLimitPerMonth = 10000;

    protected function getBaseUrl()
    {
        return config('services.google_maps.base_url');
    }

    public function geocode(string $address): ?GeocodingResult
    {
        if (isset($this->addressesCache[$address])) {
            return $this->addressesCache[$address];
        }

        if (!$this->canMakeRequest()) {
            Log::warning("Google Maps API request limit reached for the month. Cannot geocode address: {$address}");

            return null;
        }

        $response = $this->request('get', 'geocode/json', [
            'address' => $address,
            'key' => $this->apiKey,
        ]);

        $this->incrementUsage();

        if (isset($response['results'][0])) {
            $location = $response['results'][0]['geometry']['location'];

            $result = new GeocodingResult($location['lat'], $location['lng']);
            $this->addressesCache[$address] = $result;

            return $result;
        }

        return null;
    }

    protected function getUsageKey(): string
    {
        return 'google_maps_api_usage_' . date('Y_m');
    }

    protected function getUsage(): int
    {
        $cacheKey = $this->getUsageKey();

        return cache()->get($cacheKey, 0);
    }

    protected function incrementUsage(): void
    {
        $cacheKey = $this->getUsageKey();
        cache()->increment($cacheKey);
    }

    protected function canMakeRequest(): bool
    {
        return $this->getUsage() < $this->qtyLimitPerMonth;
    }
}