<?php

namespace Condoedge\Utils\Services\ComplianceValidation\Rules;

use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssueTypeEnum;
use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;
use Condoedge\Utils\Models\ComplianceValidation\ValidationExecution;
use Condoedge\Utils\Services\ComplianceValidation\ValidatableContract;
use Kompo\Elements\Element;

abstract class BaseRule implements RuleContract
{
    public function runValidation(): ValidationExecution
    {
        $startedAt = now();

        [$failingValidatables, $testedCount] = $this->findViolations();

        $now = now()->format('Y-m-d H:i:s');
        
        $complianceIssues = collect($failingValidatables)
            ->map(function (ValidatableContract $validatable) use ($now) {
                $complianceIssue = $validatable->getFailedValidationObject();
                $complianceIssue->detected_at = $now;
                $complianceIssue->type = $this->getIssueType($validatable)->value;
                $complianceIssue->resolved_at = null;
                $complianceIssue->rule_code = static::class;
                $complianceIssue->detail_message = $this->getIssueDescription($validatable);

                $data = $complianceIssue->toArray();
                $data['created_at'] = $now;
                $data['updated_at'] = $now;
                
                return $data;
            });

        $existingKeys = ComplianceIssue::where('rule_code', static::class)
            ->whereIn('validatable_id', $complianceIssues->pluck('validatable_id'))
            ->whereIn('validatable_type', $complianceIssues->pluck('validatable_type'))
            ->whereNull('resolved_at')
            ->get(['validatable_id', 'validatable_type'])
            ->map(fn($item) => $item->validatable_type . '_' . $item->validatable_id)
            ->toArray();

        $newComplianceIssues = $complianceIssues
            ->filter(function($issue) use ($existingKeys) {
                $key = $issue['validatable_type'] . '_' . $issue['validatable_id'];
                return !in_array($key, $existingKeys);
            })
            ->toArray();

        if (!empty($newComplianceIssues)) {
            ComplianceIssue::insert($newComplianceIssues);
        }

        ComplianceIssue::where('rule_code', static::class)
            ->whereNull('resolved_at')
            ->whereNotIn('validatable_id', collect($failingValidatables)->pluck('validatable_id'))
            ->update(['resolved_at' => now()]);

        $execution = new ValidationExecution();
        $execution->rule_code = static::class;
        $execution->execution_started_at = $startedAt;
        $execution->execution_ended_at = now();
        $execution->records_checked = $testedCount;
        $execution->records_failed = count($failingValidatables);
        $execution->save();

        return $execution;
    }

    abstract protected function getIssueDescription(ValidatableContract $validatable): string;
    abstract protected function getIssueType(ValidatableContract $validatable): ComplianceIssueTypeEnum;

    abstract protected function findViolations();

    public function individualValidationDetails(ValidatableContract $validatable): Element
    {
        throw new \Exception('Not available for this rule type.');
    }
}