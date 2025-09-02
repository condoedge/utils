<?php

namespace Condoedge\Utils\Services\Maps;

interface GeocodingBatchService extends GeocodingService
{
    public function geocodeBatch(array $addresses): array;
}