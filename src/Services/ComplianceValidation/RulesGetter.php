<?php

namespace Condoedge\Utils\Services\ComplianceValidation;

use Carbon\Carbon;
use Condoedge\Utils\Services\ComplianceValidation\Rules\RuleContract;
use Condoedge\Utils\Services\ComplianceValidation\ScheduledRuleContract;

class RulesGetter
{
    public function parseRules(array $rules): array
    {
        $parsedRules = [];
        foreach ($rules as $rule) {
            if (gettype($rule) == 'string') {
                $rule = app($rule);
            }
            $parsedRules[] = $rule;
        }

        foreach ($parsedRules as $rule) {
            if (!($rule instanceof RuleContract)) {
                throw new \Exception('Invalid rule provided: ' . gettype($rule));
            }
        }

        return $parsedRules;
    }

    public function rulesWithLabels(array $rules)
    {
        $rules = $this->parseRules($rules);
        $labeledRules = [];

        foreach ($rules as $rule) {
            $labeledRules[$rule->getCode()] = $rule->getName();
        }

        return $labeledRules;
    }

    public function getAllDefaultRules()
    {
        return $this->getAllRulesFromConfig();
    }

    public function getDefaultRulesWithLabels()
    {
        $rules = $this->getAllDefaultRules();
        
        return $this->rulesWithLabels($rules);
    }

    protected function getAllRulesFromConfig()
    {
        return $this->parseRules(config('kompo-utils.compliance-validation-rules', []));
    }

    public function getRuleFromCode($ruleCode)
    {
        $rules = $this->getAllDefaultRules();
        
        foreach ($rules as $rule) {
            if ($rule->getCode() === $ruleCode) {
                return $rule;
            }
        }
        
        return null;
    }

    /**
     * Get rules that should run at a specific date/time
     * 
     * @param Carbon|null $dateTime The date/time to check (defaults to now)
     * @return RuleContract[]
     */
    public function getRulesScheduledFor(?Carbon $dateTime = null): array
    {
        $dateTime = $dateTime ?? now();
        $allRules = $this->getAllDefaultRules();
        
        return array_filter($allRules, function (RuleContract $rule) use ($dateTime) {
            return $rule instanceof ScheduledRuleContract && $rule->shouldRunAt($dateTime);
        });
    }

    /**
     * Get rules filtered by frequency
     * 
     * @param string $frequency Frequency to filter by (daily, weekly, monthly, etc.)
     * @return RuleContract[]
     */
    public function getRulesByFrequency(string $frequency): array
    {
        $allRules = $this->getAllDefaultRules();
        
        return array_filter($allRules, function (RuleContract $rule) use ($frequency) {
            return $rule instanceof ScheduledRuleContract && $rule->getFrequency() === $frequency;
        });
    }

    /**
     * Get all rules with their schedule information
     * 
     * @return array Array with rule codes as keys and schedule info as values
     */
    public function getRulesWithSchedules(): array
    {
        $allRules = $this->getAllDefaultRules();
        $rulesWithSchedules = [];
        
        foreach ($allRules as $rule) {
            $scheduleInfo = [
                'name' => $rule->getName(),
                'code' => $rule->getCode(),
            ];
            
            if ($rule instanceof ScheduledRuleContract) {
                $scheduleInfo['frequency'] = $rule->getFrequency();
                $scheduleInfo['schedule_description'] = $rule->getScheduleDescription();
                $scheduleInfo['next_run'] = $rule->getNextRunTime(now());
            } else {
                $scheduleInfo['frequency'] = 'manual';
                $scheduleInfo['schedule_description'] = 'Manual execution only';
                $scheduleInfo['next_run'] = null;
            }
            
            $rulesWithSchedules[$rule->getCode()] = $scheduleInfo;
        }
        
        return $rulesWithSchedules;
    }

    /**
     * Get rules that have upcoming runs within a time period
     * 
     * @param Carbon $from Start time
     * @param Carbon $to End time
     * @return array Array of rules with their next run times
     */
    public function getRulesWithUpcomingRuns(Carbon $from, Carbon $to): array
    {
        $allRules = $this->getAllDefaultRules();
        $upcomingRules = [];
        
        foreach ($allRules as $rule) {
            if (!($rule instanceof ScheduledRuleContract)) {
                continue;
            }
            
            $nextRun = $rule->getNextRunTime($from);
            if ($nextRun && $nextRun->lte($to)) {
                $upcomingRules[] = [
                    'rule' => $rule,
                    'next_run' => $nextRun,
                    'code' => $rule->getCode(),
                    'name' => $rule->getName(),
                    'frequency' => $rule->getFrequency()
                ];
            }
        }
        
        // Sort by next run time
        usort($upcomingRules, function ($a, $b) {
            return $a['next_run']->lt($b['next_run']) ? -1 : 1;
        });
        
        return $upcomingRules;
    }

    /**
     * Get all available frequencies from rules
     * 
     * @return array Array of unique frequencies
     */
    public function getAvailableFrequencies(): array
    {
        $allRules = $this->getAllDefaultRules();
        $frequencies = ['manual']; // Always include manual
        
        foreach ($allRules as $rule) {
            if ($rule instanceof ScheduledRuleContract) {
                $frequencies[] = $rule->getFrequency();
            }
        }
        
        return array_unique($frequencies);
    }
}