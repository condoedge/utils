<?php

namespace Condoedge\Utils\Facades;

use Kompo\Komponents\Form\KompoModelFacade;

class TeamModel extends KompoModelFacade
{
    public static function getModelBindKey()
    {
        return 'team-model';
    }
}