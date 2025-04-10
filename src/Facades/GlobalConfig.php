<?php

namespace Condoedge\Utils\Facades;

use Condoedge\Utils\Services\GlobalConfig\GlobalConfigServiceContract;
use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(string $key, $default = null)
 *
 * @see \App\GlobalConfig\GlobalConfigServiceContract
 */
class GlobalConfig extends Facade
{
    protected static function getFacadeAccessor()
    {
        return GlobalConfigServiceContract::class;
    }
}
