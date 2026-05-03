<?php

namespace Condoedge\Utils\Events;

use Condoedge\Utils\Services\ComplianceValidation\RulesGetter;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;


class MultipleComplianceIssuesDetected
{
    use Dispatchable, SerializesModels;

    public array $failingValidatableIds;
    public array $persistedComplianceIssueIds;
    public string $ruleCode;

    /**
     * Create a new event instance
     */
    public function __construct(string $ruleCode, array $failingValidatables, array $persistedComplianceIssueIds = [])
    {
        $this->ruleCode = $ruleCode;
        $this->persistedComplianceIssueIds = $persistedComplianceIssueIds;
        // Store only IDs and morph types to prevent memory issues
        $this->failingValidatableIds = collect($failingValidatables)->map(function ($validatable) {
            return [
                'id' => $validatable->getKey(),
                'type' => $validatable->getMorphClass()
            ];
        })->toArray();
    }

    public function getRuleInstance()
    {
        return app(RulesGetter::class)->getRuleFromCode($this->ruleCode);
    }

    public function getRuleCode(): string
    {
        return $this->ruleCode;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<\Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue>
     */
    public function getPersistedIssues()
    {
        if (empty($this->persistedComplianceIssueIds)) {
            return \Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue::query()->whereRaw('1=0')->get();
        }

        return \Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue::with('validatable')
            ->whereIn('id', $this->persistedComplianceIssueIds)
            ->get();
    }

    /**
     * Reconstruct the failing validatables from stored IDs
     */
    public function getFailingValidatables(): array
    {
        $validatables = [];

        foreach ($this->failingValidatableIds as $validatableData) {
            try {
                $model = findOrFailMorphModel($validatableData['id'], $validatableData['type']);
                $validatables[] = $model;
            } catch (\Exception $e) {
                // Skip if model not found (might have been deleted)
                continue;
            }
        }
        
        return $validatables;
    }
}