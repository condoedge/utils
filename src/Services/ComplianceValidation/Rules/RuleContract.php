<?php

namespace Condoedge\Utils\Services\ComplianceValidation\Rules;

use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;
use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssueTypeEnum;
use Condoedge\Utils\Services\ComplianceValidation\ValidatableContract;
use Kompo\Elements\BaseElement;
use Kompo\Elements\Element;

interface RuleContract
{
    /**
     * Get the rule code (slug version of class name)
     */
    public function getCode(): string;

    /**
     * Get the enhanced name/description of the rule
     */
    public function getName(): string;

    /**
     * Find validatables that violate this rule
     * @return array [failingValidatables[], testedCount]
     */
    public function findViolations(): array;

    /**
     * Get description of why this validatable failed
     */
    public function getIssueDescription(ValidatableContract $validatable): string;

    /**
     * Get the type/severity of issue for this validatable
     */
    public function getIssueType(ValidatableContract $validatable): ComplianceIssueTypeEnum;

    public function runIndividualRevalidation(ComplianceIssue $complianceIssue): bool;

    public function individualValidationDetailsComponent(ComplianceIssue $complianceIssue): ?BaseElement;
}