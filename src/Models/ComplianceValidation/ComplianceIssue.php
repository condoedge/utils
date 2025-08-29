<?php

namespace Condoedge\Utils\Models\ComplianceValidation;

use Condoedge\Utils\Models\Model;
use Condoedge\Utils\Services\ComplianceValidation\ValidatableContract;

class ComplianceIssue extends Model
{
    protected $table = 'compliance_issues';
    
    protected $fillable = [
        'resolved_at',
    ];

    public function setValidatable(ValidatableContract $validatable)
    {
        $this->validatable_id = $validatable->getKey();
        $this->validatable_type = $validatable->getMorphClass();
    }
}