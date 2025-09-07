<?php

namespace Condoedge\Utils\Services\ComplianceValidation\Schedules;

use Carbon\Carbon;

class MonthlySchedule implements ScheduleContract
{
    protected int $dayOfMonth; // 1-31, or -1 for last day of month
    protected int $hour;
    protected int $minute;

    public function __construct(int $dayOfMonth = 1, int $hour = 2, int $minute = 0)
    {
        $this->dayOfMonth = $dayOfMonth;
        $this->hour = $hour;
        $this->minute = $minute;
    }

    public function shouldRunAt(Carbon $dateTime): bool
    {
        $targetDay = $this->dayOfMonth === -1 
            ? $dateTime->daysInMonth 
            : min($this->dayOfMonth, $dateTime->daysInMonth);

        return $dateTime->day === $targetDay 
            && $dateTime->hour === $this->hour 
            && $dateTime->minute === $this->minute;
    }

    public function getNextRunTime(Carbon $after): ?Carbon
    {
        $next = $after->copy()->setTime($this->hour, $this->minute, 0);
        
        // Calculate target day for current month
        $targetDay = $this->dayOfMonth === -1 
            ? $next->daysInMonth 
            : min($this->dayOfMonth, $next->daysInMonth);
        
        $next->setDay($targetDay);
        
        // If already passed this month, go to next month
        if ($next->lte($after)) {
            $next->addMonth();
            $targetDay = $this->dayOfMonth === -1 
                ? $next->daysInMonth 
                : min($this->dayOfMonth, $next->daysInMonth);
            $next->setDay($targetDay);
        }
        
        return $next;
    }

    public function getDescription(): string
    {
        if ($this->dayOfMonth === -1) {
            return sprintf('Monthly on last day at %02d:%02d', $this->hour, $this->minute);
        }
        
        return sprintf('Monthly on day %d at %02d:%02d', $this->dayOfMonth, $this->hour, $this->minute);
    }

    public function getFrequency(): string
    {
        return 'monthly';
    }
}