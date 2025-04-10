<?php

namespace Condoedge\Utils\Services\GlobalConfig;

abstract class AbstractConfigService implements GlobalConfigServiceContract
{
    public function getOrFail(string $key)
    {
        $value = $this->get($key);

        if ($value === null) {
            throw new \Exception("Config key '$key' not found.");
        }

        return $value;
    }
}