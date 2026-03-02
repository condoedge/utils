<?php

namespace Condoedge\Utils\Services\GlobalConfig;

class HybridGlobalConfigService extends AbstractConfigService
{
    public function __construct(
        protected DbGlobalConfigService $dbService,
        protected FileGlobalConfigService $fileService
    ) {
    }
    
    public function forget(string $key)
    {
        $this->dbService->forget($key);
        $this->fileService->forget($key);
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, $default = null)
    {
        $value = $this->dbService->get($key, null);

        if ($value !== null) {
            return $value;
        }

        return $this->fileService->get($key, $default);
    }

    /**
     * @inheritDoc
     */
    public function has(string $key)
    {
        return $this->dbService->has($key) || $this->fileService->has($key);
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, $value)
    {
        $config = $this->dbService->set($key, $value);

        return $config;
    }
}
