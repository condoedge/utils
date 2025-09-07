<?php

namespace Condoedge\Utils\Services\ComplianceValidation;

use Carbon\Carbon;
use Condoedge\Utils\Models\ComplianceValidation\ValidationExecution;
use Condoedge\Utils\Services\ComplianceValidation\Rules\RuleContract;

class ComplianceValidationService
{
    protected RulesProcessor $processor;
    protected ComplianceValidationRouter $router;
    protected RulesGetter $rulesGetter;

    public function __construct(RulesProcessor $processor, ComplianceValidationRouter $router, RulesGetter $rulesGetter)
    {
        $this->processor = $processor;
        $this->router = $router;
        $this->rulesGetter = $rulesGetter;
    }

    public function validateDefaultRules(): array
    {
        return $this->validate($this->getAllDefaultRules());
    }

    /**
     * Validate the given rules.
     * @param RuleContract[]|string[] $rules
     * @return ValidationExecution[]
     */
    public function validate(array $rules): array
    {
        $executions = [];

        $rules = $this->parseRules($rules);

        foreach ($rules as $rule) {
            $executions[] = $this->processor->processRule($rule);
        }
        
        return $executions;
    }

    public function parseRules(array $rules): array
    {
        return $this->rulesGetter->parseRules($rules);
    }

    public function getAllDefaultRules()
    {
        return $this->rulesGetter->getAllDefaultRules();
    }

    public function setRoutes()
    {
        $this->router->setRoutes();
    }

    /**
     * Validate only rules scheduled to run at a specific date/time
     * @param Carbon|null $dateTime The date/time to check (defaults to now)
     * @return ValidationExecution[]
     */
    public function validateScheduledRules(?Carbon $dateTime = null): array
    {
        $dateTime = $dateTime ?? now();
        $scheduledRules = $this->rulesGetter->getRulesScheduledFor($dateTime);
        
        return $this->validate($scheduledRules);
    }

    /**
     * Validate rules by frequency (daily, weekly, monthly, etc.)
     * @param string $frequency The frequency to run
     * @return ValidationExecution[]
     */
    public function validateRulesByFrequency(string $frequency): array
    {
        $rules = $this->rulesGetter->getRulesByFrequency($frequency);
        
        return $this->validate($rules);
    }

    /**
     * Get all rules with their schedule information
     * @return array Array with rule codes as keys and schedule info as values
     */
    public function getRulesWithSchedules(): array
    {
        return $this->rulesGetter->getRulesWithSchedules();
    }

    /**
     * Get rules that have upcoming runs within a time period
     * @param Carbon $from Start time
     * @param Carbon $to End time
     * @return array Array of rules with their next run times
     */
    public function getUpcomingRuns(Carbon $from, Carbon $to): array
    {
        return $this->rulesGetter->getRulesWithUpcomingRuns($from, $to);
    }

    /**
     * Get all available frequencies
     * @return array Array of unique frequencies
     */
    public function getAvailableFrequencies(): array
    {
        return $this->rulesGetter->getAvailableFrequencies();
    }
}