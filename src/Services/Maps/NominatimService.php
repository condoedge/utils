<?php

namespace Condoedge\Utils\Services\Maps;

use Condoedge\Utils\Services\AbstractApiClientService;
use Illuminate\Support\Facades\Log;

class NominatimService extends AbstractApiClientService implements GeocodingService
{
    protected $addressesCache = [];

    protected $acceptJson = false; // It doesn't work if we send it in the headers

    protected function getBaseUrl()
    {
        return config('services.nominatim.base_url');
    }

    public function geocode(string|array $address): ?GeocodingResult
    {
        $stringArray = is_array($address) ? implode(', ', $address) : $address;

        if (isset($this->addressesCache[$stringArray])) {
            return $this->addressesCache[$stringArray];
        }

        if (!is_array($address)) {
            return $this->fetchAddress($address);
        } else {
            $variants = [
                ['address', 'city', 'state', 'postal_code', 'country'],
                ['address', 'state', 'postal_code', 'country'],
                ['half_address', 'city', 'state', 'postal_code', 'country'],
                ['half_address', 'state', 'postal_code', 'country'],
                ['city', 'state', 'postal_code', 'country'],
                ['state', 'postal_code', 'country'],
            ];

            $explodeStreet = explode(', ', $address['address'] ?? '');
            $address['half_address'] = $explodeStreet[1] ?? $explodeStreet[0];

            foreach ($variants as $variantKeys) {
                $variant = $this->getVariant($address, $variantKeys);

                if (count($variant)) {
                    $result = $this->fetchAddress(implode(', ', $variant));
                    if ($result) {
                        return $result;
                    }
                }
            }
        }

        Log::warning('NominatimService: No results found for address: ' . $stringArray);

        return null;
    }

    public function acceptsBatch(): bool
    {
        return false;
    }

    protected function getVariant(array $address, array $variantKeys): array
    {
        $variant = [];
        foreach ($variantKeys as $key) {
            if (isset($address[$key]) && $address[$key]) {
                $variant[] = $address[$key];
            }
        }

        return $variant;
    }

    protected function fetchAddress(string $address): ?GeocodingResult
    {
        $address = $this->sanitizeAddress($address);

        $response = $this->request('get', 'search', [
            'q' => $address,
            'format' => 'jsonv2',
            'countrycodes' => 'ca',
            'addressdetails' => 1,
            'limit' => 1,
        ]);

        if (isset($response[0])) {
            $location = $response[0];

            $result = new GeocodingResult($location['lat'], $location['lon']);
            $this->addressesCache[$address] = $result;

            return $result;
        }

        return null;
    }

    protected function sanitizeAddress(string $address): string
    {
        // Remove any remaining commas
        $address = str_replace(',', ' ', $address);

        // Remove multiple spaces
        $address = preg_replace('/\s+/', ' ', $address);

        // Trim leading and trailing spaces
        $address = trim($address);

        return $address;
    }
}