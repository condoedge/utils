<?php

namespace Condoedge\Utils\Services\ComplianceValidation\Rules;

use Condoedge\Utils\Models\ComplianceValidation\ComplianceErrorTypesEnum;
use Condoedge\Utils\Models\ComplianceValidation\ComplianceValidation;
use Condoedge\Utils\Models\ComplianceValidation\ValidationExecution;
use Condoedge\Utils\Services\ComplianceValidation\ValidatableContract;
use Kompo\Elements\Element;

abstract class BaseRule implements RuleContract
{
    public function handle(): ValidationExecution
    {
        $startedAt = now();

        [$failingValidatables, $testedCount] = $this->execute();

        $now = now()->format('Y-m-d H:i:s');
        
        $notComplianceValidatable = collect($failingValidatables)
            ->map(function (ValidatableContract $validatable) use ($now) {
                $notComplianceValidatable = $validatable->getFailedValidationObject();
                $notComplianceValidatable->failed_at = $now;
                $notComplianceValidatable->type = $this->getTypeError($validatable)->value;
                $notComplianceValidatable->back_to_valid_at = null;
                $notComplianceValidatable->rule_code = static::class;
                $notComplianceValidatable->detail_message = $this->detailMessage($validatable);

                $data = $notComplianceValidatable->toArray();
                $data['created_at'] = $now;
                $data['updated_at'] = $now;
                
                return $data;
            });

        $existingKeys = ComplianceValidation::where('rule_code', static::class)
            ->whereIn('validatable_id', $notComplianceValidatable->pluck('validatable_id'))
            ->whereIn('validatable_type', $notComplianceValidatable->pluck('validatable_type'))
            ->whereNull('back_to_valid_at')
            ->get(['validatable_id', 'validatable_type'])
            ->map(fn($item) => $item->validatable_type . '_' . $item->validatable_id)
            ->toArray();

        $newValidations = $notComplianceValidatable
            ->filter(function($validation) use ($existingKeys) {
                $key = $validation['validatable_type'] . '_' . $validation['validatable_id'];
                return !in_array($key, $existingKeys);
            })
            ->toArray();

        if (!empty($newValidations)) {
            ComplianceValidation::insert($newValidations);
        }

        ComplianceValidation::where('rule_code', static::class)
            ->whereNull('back_to_valid_at')
            ->whereNotIn('validatable_id', collect($failingValidatables)->pluck('validatable_id'))
            ->update(['back_to_valid_at' => now()]);

        $execution = new ValidationExecution();
        $execution->rule_code = static::class;
        $execution->execution_started_at = $startedAt;
        $execution->execution_ended_at = now();
        $execution->records_checked = $testedCount;
        $execution->records_failed = count($failingValidatables);
        $execution->save();

        return $execution;
    }

    abstract protected function detailMessage(ValidatableContract $validatable): string;
    abstract protected function getTypeError(ValidatableContract $validatable): ComplianceErrorTypesEnum;

    abstract protected function execute();

    public function individualValidationDetails(ValidatableContract $validatable): Element
    {
        throw new \Exception('Not available for this rule type.');
    }
}