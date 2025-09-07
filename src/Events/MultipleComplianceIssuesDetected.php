<?php

namespace Condoedge\Utils\Events;

use Condoedge\Utils\Services\ComplianceValidation\RulesGetter;
use Illuminate\Foundation\Events\Dispatchable;

class MultipleComplianceIssuesDetected
{
    use Dispatchable;
    // use SerializesModels; Removed from now, because model is not saved yet when this is dispatched

    public array $failingValidatables;
    public string $ruleCode;

    /**
     * Create a new event instance
     */
    public function __construct(string $ruleCode, array $failingValidatables)
    {
        $this->failingValidatables = $failingValidatables;
        $this->ruleCode = $ruleCode;
    }

    public function getRuleInstance()
    {
        return app(RulesGetter::class)->getRuleFromCode($this->ruleCode);
    }

    public function getRuleCode(): string
    {
        return $this->ruleCode;
    }
}