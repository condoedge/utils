<?php

namespace Condoedge\Utils\Events;

use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;
use Condoedge\Utils\Services\ComplianceValidation\ValidatableContract;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;


class ComplianceIssueDetected
{
    use Dispatchable, SerializesModels;

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