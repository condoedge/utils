<?php

namespace Condoedge\Utils\Services\ComplianceValidation\Schedules;

use Carbon\Carbon;

interface ScheduleContract
{
    /**
     * Check if this schedule should run at the given date/time
     */
    public function shouldRunAt(Carbon $dateTime): bool;

    /**
     * Get the next run time after the given date/time
     */
    public function getNextRunTime(Carbon $after): ?Carbon;

    /**
     * Get human-readable description of this schedule
     */
    public function getDescription(): string;

    /**
     * Get the frequency identifier
     */
    public function getFrequency(): string;
}