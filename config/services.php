<?php

use Condoedge\Utils\Services\GlobalConfig\DbGlobalConfigService;
use Condoedge\Utils\Services\GlobalConfig\FileGlobalConfigService;

return [
    'global_config_service' => [
        'driver' => env('GLOBAL_CONFIG_SERVICE_DRIVER', 'file'),
        'drivers' => [
            'file' => [
                'class' => FileGlobalConfigService::class,
            ],
            'db' => [
                'class' => DbGlobalConfigService::class,
            ],
        ],
    ]
];