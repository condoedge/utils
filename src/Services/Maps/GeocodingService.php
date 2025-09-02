<?php

namespace Condoedge\Utils\Services\Maps;

interface GeocodingService 
{
    public function geocode(string|array $address): ?GeocodingResult;

    public function acceptsBatch(): bool;
}