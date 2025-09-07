<?php

namespace Condoedge\Utils\Services\ComplianceValidation\Schedules;

use Carbon\Carbon;

class DailySchedule implements ScheduleContract
{
    protected int $hour;
    protected int $minute;

    public function __construct(int $hour = 2, int $minute = 0)
    {
        $this->hour = $hour;
        $this->minute = $minute;
    }

    public function shouldRunAt(Carbon $dateTime): bool
    {
        return $dateTime->hour === $this->hour && $dateTime->minute === $this->minute;
    }

    public function getNextRunTime(Carbon $after): ?Carbon
    {
        $next = $after->copy()->setTime($this->hour, $this->minute, 0);
        
        // If the time has already passed today, schedule for tomorrow
        if ($next->lte($after)) {
            $next->addDay();
        }
        
        return $next;
    }

    public function getDescription(): string
    {
        return sprintf('Daily at %02d:%02d', $this->hour, $this->minute);
    }

    public function getFrequency(): string
    {
        return 'daily';
    }
}