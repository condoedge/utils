<?php

namespace Condoedge\Utils\Events;

use Condoedge\Utils\Services\ComplianceValidation\RulesGetter;
use Illuminate\Database\Eloquent\Relations\Relation;
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
     * Stored ids grouped by morph type. Free of DB work, so a consumer can
     * resolve per-context behaviour before deciding to hydrate anything.
     */
    public function getFailingValidatableIdsByType(): array
    {
        $grouped = [];

        foreach ($this->failingValidatableIds as $validatableData) {
            $grouped[$validatableData['type']][] = $validatableData['id'];
        }

        return $grouped;
    }

    /**
     * Load one morph type's failing rows in a single query. Rows deleted since
     * detection simply drop out, as they did when this loaded them one by one.
     */
    public function loadValidatables(string $type, array $ids): array
    {
        $class = Relation::morphMap()[$type] ?? null;

        if (!$class || empty($ids)) {
            return [];
        }

        $model = new $class();

        return $class::query()
            ->whereIntegerInRaw($model->getQualifiedKeyName(), $ids)
            ->get()
            ->all();
    }

    /**
     * Reconstruct the failing validatables from stored IDs
     */
    public function getFailingValidatables(): array
    {
        $validatables = [];

        foreach ($this->getFailingValidatableIdsByType() as $type => $ids) {
            $validatables = array_merge($validatables, $this->loadValidatables($type, $ids));
        }

        return $validatables;
    }
}