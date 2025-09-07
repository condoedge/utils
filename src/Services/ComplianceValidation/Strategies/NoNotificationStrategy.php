<?php

namespace Condoedge\Utils\Services\ComplianceValidation\Strategies;

use Condoedge\Utils\Services\ComplianceValidation\NotificationStrategyContract;
use Condoedge\Utils\Services\ComplianceValidation\ValidatableContract;

class NoNotificationStrategy implements NotificationStrategyContract
{
    public function getNotifiables(ValidatableContract $validatable, string $ruleCode): array
    {
        return [];
    }
}