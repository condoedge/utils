<?php

namespace Condoedge\Utils\Services\Maps;

interface GeocodingService 
{
    public function geocode(string $address): ?GeocodingResult;
}