<?php

use Condoedge\Utils\Services\ComplianceValidation\ComplianceValidationService;
use Condoedge\Utils\Services\ComplianceValidation\RulesGetter;
use Condoedge\Utils\Services\Maps\GoogleMapsService;

if (!function_exists('complianceService')) {
    function complianceService(): ComplianceValidationService
    {
        return app(config('services.compliance-validation-service'));
    }
}

if (!function_exists('complianceRulesService')) {
    function complianceRulesService(): RulesGetter
    {
        return app(RulesGetter::class);
    }
}

if (!function_exists('googleMapsService')) {
    function googleMapsService(): GoogleMapsService
    {
        return app(GoogleMapsService::class);
    }
}

if (!function_exists('geocodingService')) {
    function geocodingService(): \Condoedge\Utils\Services\Maps\GeocodingService
    {
        return app(\Condoedge\Utils\Services\Maps\GeocodingService::class);
    }
}