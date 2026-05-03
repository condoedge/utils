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
}
