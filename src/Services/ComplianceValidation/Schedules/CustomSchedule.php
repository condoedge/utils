<?php

namespace Condoedge\Utils\Services\ComplianceValidation\Schedules;

use Carbon\Carbon;

class CustomSchedule implements ScheduleContract
{
    protected \Closure $shouldRunCallback;
    protected \Closure $nextRunCallback;
    protected string $description;
    protected string $frequency;

    public function __construct(
        \Closure $shouldRunCallback,
        \Closure $nextRunCallback,
        string $description,
        string $frequency = 'custom'
    ) {
        $this->shouldRunCallback = $shouldRunCallback;
        $this->nextRunCallback = $nextRunCallback;
        $this->description = $description;
        $this->frequency = $frequency;
    }

    public function shouldRunAt(Carbon $dateTime): bool
    {
        return call_user_func($this->shouldRunCallback, $dateTime);
    }

    public function getNextRunTime(Carbon $after): ?Carbon
    {
        return call_user_func($this->nextRunCallback, $after);
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getFrequency(): string
    {
        return $this->frequency;
    }

    /**
     * Create a schedule that runs every N hours
     */
    public static function everyHours(int $hours, int $minute = 0): self
    {
        return new self(
            function (Carbon $dateTime) use ($hours, $minute) {
                return $dateTime->minute === $minute && ($dateTime->hour % $hours) === 0;
            },
            function (Carbon $after) use ($hours, $minute) {
                $next = $after->copy()->setMinute($minute)->setSecond(0);
                
                // Find next hour that's divisible by $hours
                while (($next->hour % $hours) !== 0 || $next->lte($after)) {
                    $next->addHour();
                }
                
                return $next;
            },
            "Every {$hours} hours at minute {$minute}",
            "every-{$hours}h"
        );
    }

    /**
     * Create a schedule that runs on specific days of the month
     */
    public static function onDaysOfMonth(array $days, int $hour = 2, int $minute = 0): self
    {
        return new self(
            function (Carbon $dateTime) use ($days, $hour, $minute) {
                return in_array($dateTime->day, $days) 
                    && $dateTime->hour === $hour 
                    && $dateTime->minute === $minute;
            },
            function (Carbon $after) use ($days, $hour, $minute) {
                $next = $after->copy()->setTime($hour, $minute, 0);
                
                // Find next valid day
                while (!in_array($next->day, $days) || $next->lte($after)) {
                    $next->addDay();
                    
                    // Reset time if we moved to a different day
                    if ($next->hour !== $hour || $next->minute !== $minute) {
                        $next->setTime($hour, $minute, 0);
                    }
                }
                
                return $next;
            },
            'On days ' . implode(', ', $days) . " at {$hour}:{$minute}",
            'custom-days'
        );
    }

    /**
     * Create a schedule that runs on business days only
     */
    public static function businessDays(int $hour = 9, int $minute = 0): self
    {
        return new self(
            function (Carbon $dateTime) use ($hour, $minute) {
                return $dateTime->isWeekday() 
                    && $dateTime->hour === $hour 
                    && $dateTime->minute === $minute;
            },
            function (Carbon $after) use ($hour, $minute) {
                $next = $after->copy()->setTime($hour, $minute, 0);
                
                // Find next business day
                while (!$next->isWeekday() || $next->lte($after)) {
                    $next->addDay();
                }
                
                return $next->setTime($hour, $minute, 0);
            },
            "Business days at {$hour}:{$minute}",
            'business-days'
        );
    }
}