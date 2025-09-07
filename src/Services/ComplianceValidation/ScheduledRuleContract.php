<?php

namespace Condoedge\Utils\Services\ComplianceValidation;

use Carbon\Carbon;

interface ScheduledRuleContract
{
    /**
     * Determine if this rule should run on the given date/time
     * 
     * @param Carbon $dateTime The date/time to check against
     * @return bool True if the rule should run, false otherwise
     */
    public function shouldRunAt(Carbon $dateTime): bool;

    /**
     * Get the next scheduled run time after the given date/time
     * 
     * @param Carbon $after The date/time to find the next run after
     * @return Carbon|null The next run time, or null if no future runs
     */
    public function getNextRunTime(Carbon $after): ?Carbon;

    /**
     * Get a human-readable description of the schedule
     * 
     * @return string Schedule description (e.g., "Daily at 2:00 AM", "Weekly on Mondays")
     */
    public function getScheduleDescription(): string;

    /**
     * Get the frequency key for this rule (for grouping and filtering)
     * 
     * @return string Frequency key (e.g., 'daily', 'weekly', 'monthly', 'custom')
     */
    public function getFrequency(): string;
}