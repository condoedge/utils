<?php

namespace Condoedge\Utils\Services\ComplianceValidation;

use Condoedge\Utils\Events\ComplianceIssueDetected;
use Condoedge\Utils\Events\MultipleComplianceIssuesDetected;
use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;
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

        $complianceIssuesData = $this->createComplianceIssuesData($rule, $failingValidatables);
        $this->repository->syncIssues($rule->getCode(), $complianceIssuesData, $failingValidatables);

        $persistedIssues = $this->loadPersistedIssues($rule->getCode(), $failingValidatables);

        $this->dispatchPerIssueEvents($rule, $failingValidatables, $persistedIssues);
        event(new MultipleComplianceIssuesDetected($rule->getCode(), $failingValidatables, $persistedIssues->pluck('id')->all()));

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
                $complianceIssue->extra_data = $rule->getComplianceIssueExtraData($validatable);

                // getAttributes() preserves the JSON-encoded extra_data the cast wrote
                // on assignment; toArray() would re-decode it and break the bulk insert.
                $data = $complianceIssue->getAttributes();
                $data['created_at'] = $now;
                $data['updated_at'] = $now;

                return $data;
            });
    }

    /**
     * Re-fetch the open issues for this run so listeners get saved models with ids.
     * Keyed by "morphClass:id" for fast lookup against validatables.
     */
    protected function loadPersistedIssues(string $ruleCode, array $failingValidatables): Collection
    {
        if (empty($failingValidatables)) {
            return collect();
        }

        $validatableIds = collect($failingValidatables)->pluck('id')->all();
        $validatableTypes = collect($failingValidatables)->map->getMorphClass()->unique()->all();

        return ComplianceIssue::where('rule_code', $ruleCode)
            ->whereNull('resolved_at')
            ->whereIn('validatable_id', $validatableIds)
            ->whereIn('validatable_type', $validatableTypes)
            ->get()
            ->keyBy(fn (ComplianceIssue $issue) => $issue->validatable_type . ':' . $issue->validatable_id);
    }

    protected function dispatchPerIssueEvents(RuleContract $rule, array $failingValidatables, Collection $persistedIssues): void
    {
        foreach ($failingValidatables as $validatable) {
            $key = $validatable->getMorphClass() . ':' . $validatable->getKey();
            $issue = $persistedIssues->get($key);

            if (!$issue) {
                continue;
            }

            event(new ComplianceIssueDetected($issue, $validatable, $rule->getCode()));
        }
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