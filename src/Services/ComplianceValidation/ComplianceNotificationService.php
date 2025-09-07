<?php

namespace Condoedge\Utils\Services\ComplianceValidation;

use Condoedge\Utils\Events\ComplianceIssueDetected;
use Condoedge\Utils\Events\MultipleComplianceIssuesDetected;

abstract class ComplianceNotificationService
{
    /**
     * Send a single compliance notification to a notifiable
     */
    abstract public function sendSingleNotification($notifiable, ComplianceIssueDetected $event): void;

    /**
     * Send a batch compliance notification to a notifiable with multiple validatables
     */
    abstract public function sendBatchNotification($notifiable, MultipleComplianceIssuesDetected $event, array $validatables): void;
}