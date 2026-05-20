<?php

namespace Condoedge\Utils\Services\ComplianceValidation;

use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;

interface ValidatableContract
{
    public function getFailedValidationObject(): ComplianceIssue;
    public function scopeSearch($query, $term);
    public function validatableDisplayName(): string;

    /**
     * Human-readable, type-level label for this validatable kind
     * (e.g. "People", "Teams"). Used to group/label the rules catalog.
     */
    public static function validatableTypeName(): string;

    // Model-specific methods that ensure it's a model
    public function getKey();
    public function getMorphClass();
    public function save(array $options = []);
    public function delete();
}

