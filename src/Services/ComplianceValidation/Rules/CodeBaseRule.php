<?php

namespace Condoedge\Utils\Services\ComplianceValidation\Rules;

use Condoedge\Utils\Services\ComplianceValidation\ValidatableContract;

abstract class CodeBaseRule extends BaseRule implements RuleContract
{
    public function execute(): array
    {
        $validatables = $this->getValidatables();

        $failingValidatables = collect($validatables)
            ->filter(fn (ValidatableContract $validatable) => !$this->validate($validatable))
            ->all();

        return [$failingValidatables, count($validatables)];
    }

    /**
     * @return ValidatableContract[]
     */
    abstract protected function getValidatables(): array;
    abstract protected function validate(ValidatableContract $validatable): bool;
}