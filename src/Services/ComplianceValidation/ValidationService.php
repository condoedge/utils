<?php

namespace Condoedge\Utils\Services\ComplianceValidation;

use Condoedge\Utils\Services\ComplianceValidation\Rules\RuleContract;

class ValidationService
{

    /**
     * Validate the given rules.
     * @param RuleContract[]|string[] $rules
     * @return void
     */
    function validate(array $rules)
    {
        foreach ($rules as $rule) {
            if (gettype($rule) == 'string') {
                $rule = app($rule);
            }

            $rule->runValidation();
        }
    }
}