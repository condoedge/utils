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

    'geocodio' => [
        'base_url' => env('GEOCODIO_BASE_URL', 'https://api.geocod.io/v1.9'),
        'api_key' => env('GEOCODIO_API_KEY'),
    ],

    'google_analytics' => [
        'enabled' => env('GOOGLE_ANALYTICS_ENABLED', false),
        'measurement_id' => env('GOOGLE_ANALYTICS_MEASUREMENT_ID'),
        'property_id' => env('GOOGLE_ANALYTICS_PROPERTY_ID'),
        'credentials_path' => env('GOOGLE_ANALYTICS_CREDENTIALS_PATH',
            storage_path('app/google/analytics-credentials.json')),
    ],

    'google_tag_manager' => [
        'enabled' => env('GTM_ENABLED', false),
        'container_id' => env('GTM_CONTAINER_ID'),
    ],
];