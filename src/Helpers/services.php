<?php

use Condoedge\Utils\Services\ComplianceValidation\ComplianceValidationService;
use Condoedge\Utils\Services\ComplianceValidation\RulesGetter;

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