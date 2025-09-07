<?php

namespace Condoedge\Utils\Events;

use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;
use Condoedge\Utils\Services\ComplianceValidation\ValidatableContract;
use Illuminate\Foundation\Events\Dispatchable;

class ComplianceIssueDetected
{
    use Dispatchable;
    // use SerializesModels; Removed from now, because model is not saved yet when this is dispatched

    public ComplianceIssue $complianceIssue;
    public ValidatableContract $validatable;
    public string $ruleCode;

    /**
     * Create a new event instance
     */
    public function __construct(ComplianceIssue $complianceIssue, ValidatableContract $validatable, string $ruleCode)
    {
        $this->complianceIssue = $complianceIssue;
        $this->validatable = $validatable;
        $this->ruleCode = $ruleCode;
    }
}