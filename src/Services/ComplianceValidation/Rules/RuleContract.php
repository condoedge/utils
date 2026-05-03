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
     * Get description of why this validatable failed.
     * It can be a translatable key that accepts parameters, or a plain string.
     */
    public function getIssueDescription(ValidatableContract $validatable): string;

    /**
     * Get any extra data to be stored with the compliance issue for this rule
     * Will be stored as JSON and passed as parameter also in translation of getIssueDescription
    */
    public function getComplianceIssueExtraData(ValidatableContract $validatable): ?array;

    /**
     * Get the type/severity of issue for this validatable
     */
    public function getIssueType(ValidatableContract $validatable): ComplianceIssueTypeEnum;

    /**
     * Optional long-form context shown in the overview "problem" section.
     * Return null when the issue description (with extra_data) is enough.
     */
    public function getProblemExplanation(ValidatableContract $validatable): ?string;

    public function runIndividualRevalidation(ComplianceIssue $complianceIssue): bool;

    /**
     * Enhanced detail message for the overview, computed lazily for a single issue.
     *
     * Reserved for genuinely expensive computations (extra joins, computed counts,
     * relation walks) — the cheap stuff stays in extra_data + getIssueDescription
     * which are populated once at detection time for all failing validatables.
     * Only override when bulk-time computation would be too costly at scale
     * (e.g. 300k validatables) and the data only matters when one issue is viewed.
     *
     * Return null to fall back to ComplianceIssue::getTranslatedDetailMessage().
     */
    public function runIndividualDiagnosis(ComplianceIssue $complianceIssue): ?string;

    public function individualValidationDetailsComponent(ComplianceIssue $complianceIssue): ?BaseElement;

    /**
     * Solution handler used to render the "Solution" section of the issue overview.
     * Default implementation in BaseRule wraps individualValidationDetailsComponent.
     * Override to plug in a richer flow (redirect, bulk action, multi-step form...).
     */
    public function getSolutionHandler(ComplianceIssue $complianceIssue): \Condoedge\Utils\Services\ComplianceValidation\Solutions\AbstractComplianceSolutionHandler;
}