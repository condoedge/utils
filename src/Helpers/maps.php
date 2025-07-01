<?php

use Kompo\Place;

function parsePlaceFromRequest($key)
{
    if(!request($key)) return null;
    
    _Place();
    
    return Place::placeToDB(request($key)[0]);
}