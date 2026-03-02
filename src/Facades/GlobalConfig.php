<?php

namespace Condoedge\Utils\Facades;

use Condoedge\Utils\Services\GlobalConfig\GlobalConfigServiceContract;
use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(string $key, $default = null)
 * @method static mixed getOrFail(string $key)
 * @method static void forget(string $key)
 * @method static bool has(string $key)
 * @method static mixed set(string $key, $value)
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
