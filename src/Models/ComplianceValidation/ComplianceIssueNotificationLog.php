<?php

namespace Condoedge\Utils\Models\ComplianceValidation;

use Condoedge\Utils\Models\Model;

class ComplianceIssueNotificationLog extends Model
{
    protected $table = 'compliance_issue_notification_logs';

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function complianceIssue()
    {
        return $this->belongsTo(ComplianceIssue::class);
    }

    public function notifiable()
    {
        return $this->morphTo();
    }

    // CALCULATED FIELDS
    public function getChannelIconAttribute(): string
    {
        return match ($this->channel) {
            'email' => 'sms',
            'sms' => 'mobile',
            'in-app', 'database' => 'notification-bing',
            default => 'notification',
        };
    }

    public function getChannelColorAttribute(): string
    {
        return match ($this->channel) {
            'email' => 'info',
            'sms' => 'warning',
            'in-app', 'database' => 'mauve',
            default => 'gray',
        };
    }
}
