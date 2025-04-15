<?php

namespace Condoedge\Utils\Facades;

trait FacadeUtils
{
    public static function getClass()
    {
        return self::getFacadeRoot()::class;
    }
}