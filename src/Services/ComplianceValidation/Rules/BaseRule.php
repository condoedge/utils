<?php

namespace Condoedge\Utils\Services\ComplianceValidation\Rules;

use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;
use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssueTypeEnum;
use Condoedge\Utils\Services\ComplianceValidation\ValidatableContract;
use Illuminate\Support\Str;
use Kompo\Elements\BaseElement;
use Kompo\Elements\Element;

abstract class BaseRule implements RuleContract
{
    /**
     * Get the rule code (snake_case version of class name)
     */
    public function getCode(): string
    {
        return Str::snake(class_basename(static::class));
    }

    /**
     * Find validatables that violate this rule
     * @return array [failingValidatables[], testedCount]
     */
    abstract public function findViolations(): array;

    /**
     * Get description of why this validatable failed
     */
    abstract public function getIssueDescription(ValidatableContract $validatable): string;

    /**
     * Get the type/severity of issue for this validatable
     */
    abstract public function getIssueType(ValidatableContract $validatable): ComplianceIssueTypeEnum;

    abstract public function individualRevalidate(ComplianceIssue $complianceIssue): bool;

    public function runIndividualRevalidation(ComplianceIssue $complianceIssue): bool
    {
        if ($this->individualRevalidate($complianceIssue)) {
            $complianceIssue->markAsResolved();
            return true;
        }

        return false;
    }

    public function individualValidationDetailsComponent(ComplianceIssue $complianceIssue): ?BaseElement
    {
        return _Rows();
    }
}