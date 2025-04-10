<?php

namespace Condoedge\Utils\Services\GlobalConfig;

use GlobalConfig;

class DbGlobalConfigService extends AbstractConfigService
{
    /**
     * @inheritDoc
     */
    public function forget(string $key)
    {
        GlobalConfig::where('key', $key)->delete();
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, $default = null)
    {
        $config = GlobalConfig::where('key', $key)->first();

        if ($config) {
            return $config->value;
        }

        return $default;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key)
    {
        return GlobalConfig::where('key', $key)->exists();
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, $value)
    {
        $config = GlobalConfig::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        return $config;
    }
}
