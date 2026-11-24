<?php

namespace Condoedge\Utils\Services\GlobalConfig;

use Condoedge\Utils\Models\GlobalConfig;

class DbGlobalConfigService extends AbstractConfigService
{
    /**
     * Keys already read: [value] when the row exists, [] when it does not. The service is bound scoped, so this
     * lasts one request, one queued job or one command, and a long-lived worker reads the table again per job.
     */
    protected array $read = [];

    /**
     * @inheritDoc
     */
    public function forget(string $key)
    {
        GlobalConfig::where('key', $key)->delete();

        unset($this->read[$key]);
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, $default = null)
    {
        if (!array_key_exists($key, $this->read)) {
            $config = GlobalConfig::where('key', $key)->first();

            $this->read[$key] = $config ? [$config->value] : [];
        }

        return $this->read[$key] ? $this->read[$key][0] : $default;
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

        $this->read[$key] = [$config->value];

        return $config;
    }
}
