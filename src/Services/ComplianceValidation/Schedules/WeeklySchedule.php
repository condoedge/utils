<?php

namespace Condoedge\Utils\Services\ComplianceValidation\Schedules;

use Carbon\Carbon;

class WeeklySchedule implements ScheduleContract
{
    protected int $dayOfWeek; // 0 = Sunday, 1 = Monday, etc.
    protected int $hour;
    protected int $minute;

    public function __construct(int $dayOfWeek = 1, int $hour = 2, int $minute = 0)
    {
        $this->dayOfWeek = $dayOfWeek;
        $this->hour = $hour;
        $this->minute = $minute;
    }

    public function shouldRunAt(Carbon $dateTime): bool
    {
        return $dateTime->dayOfWeek === $this->dayOfWeek 
            && $dateTime->hour === $this->hour 
            && $dateTime->minute === $this->minute;
    }

    public function getNextRunTime(Carbon $after): ?Carbon
    {
        $next = $after->copy()->setTime($this->hour, $this->minute, 0);
        
        // Find the next occurrence of the target day of week
        while ($next->dayOfWeek !== $this->dayOfWeek || $next->lte($after)) {
            $next->addDay();
        }
        
        return $next;
    }

    public function getDescription(): string
    {
        $localizedDay = Carbon::now()->locale(app()->getLocale())->startOfWeek(Carbon::SUNDAY)->addDays($this->dayOfWeek)->dayName;
        
        return __('compliance.schedule.weekly-on-day-at', [
            'day'  => $localizedDay,
            'time' => sprintf('%02d:%02d', $this->hour, $this->minute),
        ]);
    }

    public function getFrequency(): string
    {
        return 'weekly';
    }
}