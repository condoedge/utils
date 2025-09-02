<?php

use Condoedge\Utils\Services\ComplianceValidation\ComplianceValidationService;
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
    ],

    'compliance-validation-service' => ComplianceValidationService::class,

    'google_maps' => [
        'base_url' => env('GOOGLE_MAPS_BASE_URL', 'https://maps.googleapis.com/maps/api'),
        'api_key' => env('GOOGLE_MAPS_API_KEY', env('MIX_GOOGLE_MAPS_API_KEY')),
    ],

    'nominatim' => [
        'base_url' => env('NOMINATIM_BASE_URL', 'http://localhost:7070'),
    ],
];