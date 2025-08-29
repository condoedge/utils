<?php

namespace Condoedge\Utils\Services\ComplianceValidation;

use Condoedge\Utils\Models\ComplianceValidation\ComplianceValidation;

interface ValidatableContract
{
    public function getFailedValidationObject(): ComplianceValidation;

    // Model-specific methods that ensure it's a model
    public function getKey();
    public function getMorphClass();
    public function save(array $options = []);
    public function delete();
}

