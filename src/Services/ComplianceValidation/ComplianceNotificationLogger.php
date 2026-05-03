<?php

namespace Condoedge\Utils\Services\ComplianceValidation;

use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;
use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssueNotificationLog;

/**
 * Single entry point projects call when they dispatch a compliance notification.
 *
 * Concrete notification adapters (e.g. SISC's CommunicationAdapterToCompliances)
 * should call ComplianceNotificationLogger::log(...) for every recipient so the
 * overview can show a real history.
 */
class ComplianceNotificationLogger
{
    public function log(
        ComplianceIssue $issue,
        $notifiable = null,
        ?string $channel = null,
        ?string $recipientLabel = null,
        ?string $status = null,
        ?string $statusColor = null,
        $sentAt = null,
        ?string $errorMessage = null,
    ): ComplianceIssueNotificationLog {
        $log = new ComplianceIssueNotificationLog();
        $log->compliance_issue_id = $issue->getKey();

        if ($notifiable && method_exists($notifiable, 'getKey') && method_exists($notifiable, 'getMorphClass')) {
            $log->notifiable_id = $notifiable->getKey();
            $log->notifiable_type = $notifiable->getMorphClass();
        }

        $log->channel = $channel;
        $log->recipient_label = $recipientLabel ?? $this->resolveRecipientLabel($notifiable);
        $log->status = $status;
        $log->status_color = $statusColor;
        $log->sent_at = $sentAt ?? now();
        $log->error_message = $errorMessage;
        $log->save();

        return $log;
    }

    protected function resolveRecipientLabel($notifiable): ?string
    {
        if (!$notifiable) {
            return null;
        }

        foreach (['email', 'name', 'full_name', 'team_name'] as $attr) {
            if (isset($notifiable->{$attr})) {
                return (string) $notifiable->{$attr};
            }
        }

        return null;
    }
}
