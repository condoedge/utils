<?php

namespace Condoedge\Utils\Services\ComplianceValidation;

use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;
use Illuminate\Support\Collection;

class ComplianceIssueRepository
{
    /**
     * Synchronize compliance issues for a specific rule
     */
    public function syncIssues(string $ruleCode, Collection $issuesData, array $currentFailingValidatables): void
    {
        $newIssues = $this->filterNewIssues($ruleCode, $issuesData);
        $this->insertNewIssues($newIssues);
        $this->resolveFixedIssues($ruleCode, $currentFailingValidatables);
    }

    /**
     * Filter out existing issues to avoid duplicates
     */
    protected function filterNewIssues(string $ruleCode, Collection $issuesData): array
    {
        $existingKeys = ComplianceIssue::where('rule_code', $ruleCode)
            ->whereIn('validatable_id', $issuesData->pluck('validatable_id'))
            ->whereIn('validatable_type', $issuesData->pluck('validatable_type'))
            ->whereNull('resolved_at')
            ->get(['validatable_id', 'validatable_type'])
            ->map(fn($item) => $item->validatable_type . '_' . $item->validatable_id)
            ->toArray();

        return $issuesData
            ->filter(function($issue) use ($existingKeys) {
                $key = $issue['validatable_type'] . '_' . $issue['validatable_id'];
                return !in_array($key, $existingKeys);
            })
            ->toArray();
    }

    /**
     * Insert new compliance issues in bulk with batching to avoid placeholder limit
     */
    protected function insertNewIssues(array $newIssues): void
    {
        if (empty($newIssues)) {
            return;
        }

        // MySQL prepared statement limit is 65,535 placeholders. We must do it in batch or we'll get an error
        $batchSize = 5000;
        
        foreach (array_chunk($newIssues, $batchSize) as $batch) {
            ComplianceIssue::insert($batch);
        }
    }

    /**
     * Mark previously failing items as resolved if they're now compliant
     */
    protected function resolveFixedIssues(string $ruleCode, array $currentFailingValidatables): void
    {
        ComplianceIssue::where('rule_code', $ruleCode)
            ->whereNull('resolved_at')
            ->whereNotIn('validatable_id', collect($currentFailingValidatables)->pluck('validatable_id'))
            ->update(['resolved_at' => now()]);
    }
}