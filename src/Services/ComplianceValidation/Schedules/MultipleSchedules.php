<?php

namespace Condoedge\Utils\Services\ComplianceValidation\Schedules;

use Carbon\Carbon;

class MultipleSchedules implements ScheduleContract
{
    protected array $schedules;
    protected string $description;

    public function __construct(array $schedules, string $description = 'Multiple Schedules')
    {
        $this->schedules = $schedules;
        $this->description = $description;
    }

    public function shouldRunAt(Carbon $dateTime): bool
    {
        foreach ($this->schedules as $schedule) {
            if ($schedule->shouldRunAt($dateTime)) {
                return true;
            }
        }
        return false;
    }

    public function getNextRunTime(Carbon $after): ?Carbon
    {
        $nextRunTimes = [];

        foreach ($this->schedules as $schedule) {
            $nextRun = $schedule->getNextRunTime($after);
            if ($nextRun !== null) {
                $nextRunTimes[] = $nextRun;
            }
        }

        if (empty($nextRunTimes)) {
            return null;
        }

        return min($nextRunTimes);
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getFrequency(): string
    {
        return 'multiple';
    }
}