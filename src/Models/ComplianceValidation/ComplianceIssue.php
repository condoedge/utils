<?php

namespace Condoedge\Utils\Models\ComplianceValidation;

use Condoedge\Utils\Models\Model;
use Condoedge\Utils\Models\Traits\BelongsToTeamTrait;
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

    // SCOPES
    public function scopeSearch($query, $term)
    {
        $query->where(function ($subQuery) use ($term) {
            $subQuery->where('rule_code', 'like', wildcardSpace($term))
                     ->orWhere('detail_message', 'like', wildcardSpace($term))
                     ->orWhereHasMorph('validatable', fn($q) => $q->search($term));
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