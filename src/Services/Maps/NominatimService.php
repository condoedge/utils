<?php

namespace Condoedge\Utils\Services\Maps;

use Condoedge\Utils\Services\AbstractApiClientService;
use Illuminate\Support\Facades\Log;

class NominatimService extends AbstractApiClientService implements GeocodingService
{
    protected $addressesCache = [];

    protected function getBaseUrl()
    {
        return config('services.nominatim.base_url');
    }

    public function geocode(string $address): ?GeocodingResult
    {
        if (isset($this->addressesCache[$address])) {
            return $this->addressesCache[$address];
        }

        $response = $this->request('get', 'search', [
            'address' => $address,
        ]);

        if (isset($response['results'][0])) {
            $location = $response['results'][0];

            $result = new GeocodingResult($location['lat'], $location['lng']);
            $this->addressesCache[$address] = $result;

            return $result;
        }

        return null;
    }
}