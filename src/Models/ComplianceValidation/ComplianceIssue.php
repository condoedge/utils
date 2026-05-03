<?php

namespace Condoedge\Utils\Models\ComplianceValidation;

use Condoedge\Utils\Models\Model;
use Condoedge\Utils\Models\Traits\BelongsToTeamTrait;
use Condoedge\Utils\Services\ComplianceValidation\ComplianceNotificationLogContract;
use Condoedge\Utils\Services\ComplianceValidation\HierarchicalValidatableContract;
use Condoedge\Utils\Services\ComplianceValidation\ValidatableContract;

class ComplianceIssue extends Model
{
    use BelongsToTeamTrait;

    protected $table = 'compliance_issues';
    
    protected $fillable = [
        'resolved_at',
    ];

    protected $casts = [
        'type' => ComplianceIssueTypeEnum::class,
        'extra_data' => 'array',
    ];

    // RELATIONSHIPS
    public function validatable()
    {
        return $this->morphTo();
    }

    // CALCULATED FIELDS
    public function getRuleInstance()
    {
        return complianceRulesService()->getRuleFromCode($this->rule_code);
    }

    public function getTranslatedDetailMessage()
    {
        return __($this->detail_message, $this->extra_data ?? []);
    }

    public function lastExecution(): ?ValidationExecution
    {
        return ValidationExecution::where('rule_code', $this->rule_code)
            ->latest('execution_started_at')
            ->first();
    }

    public function validatableBreadcrumb(): array
    {
        $validatable = $this->validatable;

        if (!$validatable) {
            return [];
        }

        if ($validatable instanceof HierarchicalValidatableContract) {
            return $validatable->validatableBreadcrumb();
        }

        return [$validatable->validatableDisplayName()];
    }

    public function getNotificationLog()
    {
        return app(ComplianceNotificationLogContract::class)->forIssue($this);
    }

    public function durationLabel(): string
    {
        if (!$this->detected_at) {
            return 'compliance.overview.duration';
        }

        return $this->resolved_at
            ? 'compliance.overview.duration-resolved'
            : 'compliance.overview.duration-open';
    }

    public function durationValue(): string
    {
        if (!$this->detected_at) {
            return '-';
        }

        $detected = \Carbon\Carbon::parse($this->detected_at);
        $end = $this->resolved_at ? \Carbon\Carbon::parse($this->resolved_at) : now();

        return $detected->diffForHumans($end, ['parts' => 2, 'syntax' => \Carbon\Carbon::DIFF_ABSOLUTE]);
    }

    // SCOPES
    public function scopeSearch($query, $term)
    {
        $query->where(function ($subQuery) use ($term) {
            $subQuery->where('rule_code', 'like', wildcardSpace($term))
                     ->orWhere('detail_message', 'like', wildcardSpace($term))
                     ->orWhereHas('validatable', fn($q) => $q->search($term));
        });
    }

    // ACTIONS
    public function setValidatable(ValidatableContract $validatable)
    {
        $this->validatable_id = $validatable->getKey();
        $this->validatable_type = $validatable->getMorphClass();
    }

    public function markAsResolved()
    {
        $this->resolved_at = now();
        $this->save();
    }

    public function revalidate()
    {
        $rule = $this->getRuleInstance();

        if ($rule->runIndividualRevalidation($this)) {
            return true;
        }

        return false;
    }

    // ELEMENTS
    public function typeBadge()
    {
        return _Pill($this->type->label())->class($this->type->classes());
    }

    public function statusEl()
    {
        if ($this->resolved_at) {
            return _Pill('compliance.resolved')->class('text-positive');
        }

        return _Pill('compliance.unresolved')->class('text-danger');
    }

    public function moreDetailsElement()
    {
        if ($this->resolved_at) {
            return null;
        }

        $rule = $this->getRuleInstance();

        $detailsEl = secureCall('individualValidationDetailsComponent', $rule, $this);

        return _Rows($detailsEl);
    }
}