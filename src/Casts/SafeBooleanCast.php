<?php

namespace Workbench\App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class SafeBoolean implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        return (bool) $value;
    }

    public function set($model, string $key, $value, array $attributes)
    {
        return (bool) $value ?? false;
    }
}