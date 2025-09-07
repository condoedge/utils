<?php

namespace Condoedge\Utils\Services\ComplianceValidation;

use Condoedge\Utils\Events\ComplianceIssueDetected;
use Condoedge\Utils\Events\MultipleComplianceIssuesDetected;
use Condoedge\Utils\Models\ComplianceValidation\ValidationExecution;
use Condoedge\Utils\Services\ComplianceValidation\Rules\RuleContract;
use Illuminate\Support\Collection;

class RulesProcessor
{
    protected ComplianceIssueRepository $repository;

    public function __construct(ComplianceIssueRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Process a single rule: detect violations, persist issues, track execution
     */
    public function processRule(RuleContract $rule): ValidationExecution
    {
        $startedAt = now();
        [$failingValidatables, $testedCount] = $rule->findViolations();

        event(new MultipleComplianceIssuesDetected($rule->getCode(), $failingValidatables));

        $complianceIssuesData = $this->createComplianceIssuesData($rule, $failingValidatables);
        $this->repository->syncIssues($rule->getCode(), $complianceIssuesData, $failingValidatables);
        
        return $this->createExecutionRecord($rule, $startedAt, $testedCount, $failingValidatables);
    }

    /**
     * Create compliance issues data from failing validatables
     */
    protected function createComplianceIssuesData(RuleContract $rule, array $failingValidatables): Collection
    {
        $now = now()->format('Y-m-d H:i:s');
        
        return collect($failingValidatables)
            ->map(function (ValidatableContract $validatable) use ($rule, $now) {
                $complianceIssue = $validatable->getFailedValidationObject();
                $complianceIssue->detected_at = $now;
                $complianceIssue->type = $rule->getIssueType($validatable)->value;
                $complianceIssue->resolved_at = null;
                $complianceIssue->rule_code = $rule->getCode();
                $complianceIssue->detail_message = $rule->getIssueDescription($validatable);

                // Dispatch event for notifications (before persisting to database)
                event(new ComplianceIssueDetected($complianceIssue, $validatable, $rule->getCode()));

                $data = $complianceIssue->toArray();
                $data['created_at'] = $now;
                $data['updated_at'] = $now;
                
                return $data;
            });
    }

    /**
     * Create execution record for the rule
     */
    protected function createExecutionRecord(RuleContract $rule, $startedAt, int $testedCount, array $failingValidatables): ValidationExecution
    {
        $execution = new ValidationExecution();
        $execution->rule_code = $rule->getCode();
        $execution->execution_started_at = $startedAt;
        $execution->execution_ended_at = now();
        $execution->records_checked = $testedCount;
        $execution->records_failed = count($failingValidatables);
        $execution->save();

        return $execution;
    }
}