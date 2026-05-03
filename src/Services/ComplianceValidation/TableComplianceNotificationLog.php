<?php

namespace Condoedge\Utils\Services\ComplianceValidation;

use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;
use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssueNotificationLog;
use Illuminate\Support\Collection;

class TableComplianceNotificationLog implements ComplianceNotificationLogContract
{
    public function forIssue(ComplianceIssue $issue): Collection
    {
        return ComplianceIssueNotificationLog::query()
            ->where('compliance_issue_id', $issue->getKey())
            ->orderByDesc('sent_at')
            ->get()
            ->map(fn (ComplianceIssueNotificationLog $row) => [
                'sent_at' => $row->sent_at,
                'channel' => $row->channel,
                'channel_icon' => $this->iconForChannel($row->channel),
                'channel_color' => $this->colorForChannel($row->channel),
                'recipient' => $row->recipient_label,
                'status' => $row->status,
                'status_color' => $row->status_color,
            ]);
    }

    protected function iconForChannel(?string $channel): string
    {
        return match ($channel) {
            'email' => 'sms',
            'sms' => 'mobile',
            'in-app', 'database' => 'notification-bing',
            default => 'notification',
        };
    }

    protected function colorForChannel(?string $channel): string
    {
        return match ($channel) {
            'email' => 'info',
            'sms' => 'warning',
            'in-app', 'database' => 'mauve',
            default => 'gray',
        };
    }
}
