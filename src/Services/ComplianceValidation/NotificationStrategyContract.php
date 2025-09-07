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
}