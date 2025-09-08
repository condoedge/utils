<?php

namespace Condoedge\Utils\Services\ComplianceValidation;

interface NotificationStrategyContract
{
    /**
     * Get list of entities that should be notified for this validatable and rule combination
     * 
     * @param ValidatableContract $validatable The entity that failed validation
     * @param string $ruleCode The code of the rule that was violated
     * @return array List of notifiable entities (users, etc.)
     */
    public function getNotifiables(ValidatableContract $validatable, string $ruleCode): array;

    /**
     * Get map of notifiables to their associated validatables for batch processing
     * 
     * @param array $validatables Array of ValidatableContract entities that failed validation
     * @param string $ruleCode The code of the rule that was violated
     * @return array Map where keys are notifiable keys and values are ['notifiable' => entity, 'validatables' => array]
     */
    public function getBatchNotifiables(array $validatables, string $ruleCode): array;
}