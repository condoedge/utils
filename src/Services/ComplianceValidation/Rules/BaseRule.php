<?php

namespace Condoedge\Utils\Services\ComplianceValidation\Rules;

use Carbon\Carbon;
use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;
use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssueTypeEnum;
use Condoedge\Utils\Services\ComplianceValidation\NotificationStrategyContract;
use Condoedge\Utils\Services\ComplianceValidation\ScheduledRuleContract;
use Condoedge\Utils\Services\ComplianceValidation\Schedules\DailySchedule;
use Condoedge\Utils\Services\ComplianceValidation\Schedules\ScheduleContract;
use Condoedge\Utils\Services\ComplianceValidation\Strategies\NoNotificationStrategy;
use Condoedge\Utils\Services\ComplianceValidation\ValidatableContract;
use Illuminate\Support\Str;
use Kompo\Elements\BaseElement;

abstract class BaseRule implements RuleContract, ScheduledRuleContract
{
    protected ?ScheduleContract $schedule = null;

    /**
     * Get the rule code (snake_case version of class name)
     */
    public function getCode(): string
    {
        return Str::snake(class_basename(static::class));
    }

    /**
     * Find validatables that violate this rule
     * @return array [failingValidatables[], testedCount]
     */
    abstract public function findViolations(): array;

    /**
     * Get description of why this validatable failed
     */
    abstract public function getIssueDescription(ValidatableContract $validatable): string;

    /**
     * Get the type/severity of issue for this validatable
     */
    abstract public function getIssueType(ValidatableContract $validatable): ComplianceIssueTypeEnum;

    abstract public function individualRevalidate(ComplianceIssue $complianceIssue): bool;

    public function runIndividualRevalidation(ComplianceIssue $complianceIssue): bool
    {
        if ($this->individualRevalidate($complianceIssue)) {
            $complianceIssue->markAsResolved();
            return true;
        }

        return false;
    }

    public function individualValidationDetailsComponent(ComplianceIssue $complianceIssue): ?BaseElement
    {
        return _Rows();
    }

    // ScheduledRuleContract implementation

    /**
     * Get the schedule for this rule. Override this method to provide custom scheduling.
     */
    protected function getSchedule(): ScheduleContract
    {
        if ($this->schedule === null) {
            $this->schedule = $this->createDefaultSchedule();
        }
        
        return $this->schedule;
    }

    /**
     * Set a custom schedule for this rule
     */
    public function setSchedule(ScheduleContract $schedule): self
    {
        $this->schedule = $schedule;
        return $this;
    }

    /**
     * Create the default schedule (daily at 2 AM). Override to change default.
     */
    protected function createDefaultSchedule(): ScheduleContract
    {
        return new DailySchedule(2, 0);
    }

    /**
     * Check if this rule should run at the given date/time
     */
    public function shouldRunAt(Carbon $dateTime): bool
    {
        return $this->getSchedule()->shouldRunAt($dateTime);
    }

    /**
     * Get the next scheduled run time after the given date/time
     */
    public function getNextRunTime(Carbon $after): ?Carbon
    {
        return $this->getSchedule()->getNextRunTime($after);
    }

    /**
     * Get a human-readable description of the schedule
     */
    public function getScheduleDescription(): string
    {
        return $this->getSchedule()->getDescription();
    }

    /**
     * Get the frequency key for this rule
     */
    public function getFrequency(): string
    {
        return $this->getSchedule()->getFrequency();
    }

    // Notification strategy mapping

    /**
     * Get notification strategy for a specific validatable context
     * Override this method in rules to define custom notification mappings
     * 
     * @param string $validatableContext The context from validatable->getMorphClass()
     * @return NotificationStrategyContract|null
     */
    public function getNotificationStrategyFor(string $validatableContext): ?NotificationStrategyContract
    {
        $strategies = $this->getNotificationStrategies();
        
        return $strategies[$validatableContext] ?? $strategies['default'] ?? new NoNotificationStrategy();
    }

    /**
     * Define notification strategies for different validatable contexts
     * Override this method in rules to specify who gets notified
     * 
     * Example:
     * return [
     *     'team' => new TeamManagerNotificationStrategy(),
     *     'person' => new HRNotificationStrategy(),
     *     'default' => new NoNotificationStrategy()
     * ];
     * 
     * @return array<string, NotificationStrategyContract>
     */
    protected function getNotificationStrategies(): array
    {
        // Default: no notifications
        return [
            'default' => new NoNotificationStrategy()
        ];
    }

    /**
     * Check if this rule has notification strategies defined
     */
    public function hasNotificationStrategies(): bool
    {
        $strategies = $this->getNotificationStrategies();
        
        // Check if there are any strategies other than the default NoNotificationStrategy
        foreach ($strategies as $strategy) {
            if (!($strategy instanceof NoNotificationStrategy)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get all defined notification contexts for this rule
     */
    public function getNotificationContexts(): array
    {
        return array_keys($this->getNotificationStrategies());
    }
}