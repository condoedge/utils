<?php

use Kompo\Place;

function parsePlaceFromRequest($key)
{
    _Place();
    
    return Place::placeToDB(request($key)[0]);
}