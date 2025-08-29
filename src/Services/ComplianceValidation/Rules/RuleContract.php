<?php

namespace Condoedge\Utils\Services\ComplianceValidation\Rules;

use Condoedge\Utils\Models\ComplianceValidation\ValidationExecution;
use Condoedge\Utils\Services\ComplianceValidation\ValidatableContract;
use Kompo\Elements\Element;

interface RuleContract
{
    public function runValidation(): ValidationExecution;

    public function individualValidationDetails(ValidatableContract $validatable): Element;
}