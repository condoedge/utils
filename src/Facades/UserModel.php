<?php

namespace Condoedge\Utils\Facades;

use Kompo\Komponents\Form\KompoModelFacade;

class UserModel extends KompoModelFacade
{
    public static function getModelBindKey()
    {
        return 'user-model';
    }
}