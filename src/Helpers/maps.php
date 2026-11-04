<?php

use Kompo\Place;

function parsePlaceFromRequest($key)
{
    if(!request($key)) return null;
    
    _Place();
    
    $place = \Kompo\Core\Util::decode(request($key)[0]);

    // A value loaded by loadFormattedLabel() and posted back untouched carries the DB columns,
    // not Google's components; placeToDB() would read it by the internal keys and blank address1.
    if (is_array($place) && !isset($place['address_components'])) {
        return collect(config('kompo.places_attributes'))
            ->mapWithKeys(fn ($column, $internalKey) => [$column => $place[$column] ?? $place[$internalKey] ?? ''])
            ->all();
    }

    return Place::placeToDB($place);
}