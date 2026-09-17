<?php

namespace Condoedge\Utils\Services\ComplianceValidation;

use Condoedge\Utils\Events\ComplianceIssueDetected;
use Condoedge\Utils\Events\MultipleComplianceIssuesDetected;
use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;
use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssueTypeEnum;
use Condoedge\Utils\Models\ComplianceValidation\ValidationExecution;
use Condoedge\Utils\Services\ComplianceValidation\Rules\RuleContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class RulesProcessor
{
    protected ComplianceIssueRepository $repository;

    public function __construct(ComplianceIssueRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Process a single rule: detect violations, persist issues, track execution.
     * Null when another run of the same rule is in progress.
     */
    public function processRule(RuleContract $rule): ?ValidationExecution
    {
        // Overlapping runs would each see the same issues as new and announce them twice
        // (prod 2026-09-15: three manual runs started within 8 seconds).
        $lock = Cache::lock('compliance-rule:' . $rule->getCode(), 900);

        if (!$lock->get()) {
            return null;
        }

        try {
            return $this->runRule($rule);
        } finally {
            $lock->release();
        }
    }

    protected function runRule(RuleContract $rule): ValidationExecution
    {
        $startedAt = now();
        [$failingValidatables, $testedCount] = $rule->findViolations();

        $complianceIssuesData = $this->createComplianceIssuesData($rule, $failingValidatables);
        $newIssues = $this->repository->syncIssues($rule->getCode(), $complianceIssuesData, $failingValidatables);

        // Only issues first detected by this run are announced: still-open ones were
        // announced when they appeared, and re-sending them every run floods recipients.
        $newKeys = array_flip(array_map(fn (array $issue) => $issue['validatable_type'] . ':' . $issue['validatable_id'], $newIssues));
        $newValidatables = array_values(array_filter($failingValidatables, fn ($validatable) => isset($newKeys[$validatable->getMorphClass() . ':' . $validatable->getKey()])));

        $persistedIssues = $this->loadPersistedIssues($rule->getCode(), $newValidatables);

        $this->dispatchPerIssueEvents($rule, $newValidatables, $persistedIssues);

        if ($newValidatables) {
            event(new MultipleComplianceIssuesDetected($rule->getCode(), $newValidatables, $persistedIssues->pluck('id')->all()));
        }

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
                $complianceIssue->type = $rule->getIssueType($validatable)->value ?: ComplianceIssueTypeEnum::WARNING;
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
            ->whereIntegerInRaw('validatable_id', $validatableIds)
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