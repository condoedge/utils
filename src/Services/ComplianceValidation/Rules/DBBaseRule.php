<?php

namespace Condoedge\Utils\Services\ComplianceValidation\Rules;

use Condoedge\Utils\Services\ComplianceValidation\ValidatableContract;

abstract class DBBaseRule extends BaseRule implements RuleContract
{
    public final function findViolations(): array
    {
        $failingValidatables = $this->getFailingValidatables();
        $validatablesCount = $this->getValidatablesCount();
        
        return [$failingValidatables, $validatablesCount];
    }

    /**
     * Get the validatables that are failing the rule.
     * @return ValidatableContract[]
     */
    abstract protected function getFailingValidatables(): array;

    abstract protected function getValidatablesCount(): int;
}