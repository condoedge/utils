<?php

namespace Condoedge\Utils\Services\ComplianceValidation;

use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;
use Illuminate\Support\Collection;

interface ComplianceNotificationLogContract
{
    /**
     * Notifications related to a compliance issue.
     *
     * Each entry should expose:
     *   - sent_at      (Carbon|string)
     *   - channel      (string label, e.g. "email", "sms", "in-app")
     *   - recipient    (string)
     *   - status       (string label)
     *   - status_color (Kompo color slug: positive, warning, danger, info, mauve, ...)
     *
     * @return Collection<int, array>
     */
    public function forIssue(ComplianceIssue $issue): Collection;
}
