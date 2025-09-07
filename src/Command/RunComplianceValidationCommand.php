<?php

namespace Condoedge\Utils\Command;

use Carbon\Carbon;
use Condoedge\Utils\Services\ComplianceValidation\ComplianceValidationService;
use Illuminate\Console\Command;

class RunComplianceValidationCommand extends Command
{
    protected $signature = 'compliance:run-validation 
                           {--rules=* : Specific rule codes to run (optional)}
                           {--scheduled : Run only rules scheduled for current time}
                           {--frequency= : Run rules by frequency (daily, weekly, monthly, etc.)}
                           {--datetime= : Run rules scheduled for specific datetime (Y-m-d H:i format)}
                           {--list-schedules : List all rules with their schedules}
                           {--upcoming-runs= : Show upcoming runs within N hours}';

    protected $description = 'Run compliance validation rules with various scheduling options.';

    public function handle()
    {
        // Handle informational commands first
        if ($this->option('list-schedules')) {
            return $this->handleListSchedules();
        }

        if ($this->option('upcoming-runs')) {
            return $this->handleUpcomingRuns();
        }

        // Handle rule execution
        $service = complianceService();
        
        // Determine which rules to run based on options
        if ($this->option('scheduled')) {
            return $this->handleScheduledRules($service);
        }
        
        if ($this->option('frequency')) {
            return $this->handleFrequencyRules($service);
        }
        
        if ($this->option('datetime')) {
            return $this->handleDateTimeRules($service);
        }
        
        if ($this->option('rules')) {
            return $this->handleSpecificRules($service);
        }
        
        // Default: run all rules
        return $this->handleAllRules($service);
    }
    
    /**
     * Handle listing all rules with their schedules
     */
    protected function handleListSchedules(): void
    {
        $rulesWithSchedules = complianceService()->getRulesWithSchedules();
        
        $this->info('All compliance rules with schedules:');
        $this->line('');
        
        foreach ($rulesWithSchedules as $code => $info) {
            $this->line("• <info>{$info['name']}</info> ({$code})");
            $this->line("  Schedule: {$info['schedule_description']}");
            $this->line("  Frequency: {$info['frequency']}");
            
            if ($info['next_run']) {
                $this->line("  Next run: {$info['next_run']->format('Y-m-d H:i:s')}");
            } else {
                $this->line("  Next run: N/A");
            }
            $this->line('');
        }
    }
    
    /**
     * Handle showing upcoming runs
     */
    protected function handleUpcomingRuns(): void
    {
        $hours = (int) $this->option('upcoming-runs');
        $from = now();
        $to = now()->addHours($hours);
        
        $upcomingRuns = complianceService()->getUpcomingRuns($from, $to);
        
        $this->info("Upcoming compliance rule runs in the next {$hours} hour(s):");
        $this->line('');
        
        if (empty($upcomingRuns)) {
            $this->comment('No rules scheduled to run in the specified time period.');
            return;
        }
        
        foreach ($upcomingRuns as $run) {
            $this->line("• <info>{$run['name']}</info> ({$run['code']})");
            $this->line("  Scheduled: {$run['next_run']->format('Y-m-d H:i:s')}");
            $this->line("  Frequency: {$run['frequency']}");
            $this->line('');
        }
    }
    
    /**
     * Handle running only scheduled rules for current time
     */
    protected function handleScheduledRules(ComplianceValidationService $service): void
    {
        $this->info('Running rules scheduled for current time...');
        
        $executions = $service->validateScheduledRules();
        
        if (empty($executions)) {
            $this->comment('No rules were scheduled to run at this time.');
            return;
        }
        
        $this->info('Validation completed. ' . count($executions) . ' scheduled rule(s) executed.');
        $this->displayExecutionResults($executions);
    }
    
    /**
     * Handle running rules by frequency
     */
    protected function handleFrequencyRules(ComplianceValidationService $service): void
    {
        $frequency = $this->option('frequency');
        $this->info("Running rules with frequency: {$frequency}");
        
        $executions = $service->validateRulesByFrequency($frequency);
        
        if (empty($executions)) {
            $this->comment("No rules found with frequency: {$frequency}");
            return;
        }
        
        $this->info('Validation completed. ' . count($executions) . ' rule(s) with frequency "' . $frequency . '" executed.');
        $this->displayExecutionResults($executions);
    }
    
    /**
     * Handle running rules for specific datetime
     */
    protected function handleDateTimeRules(ComplianceValidationService $service): void
    {
        try {
            $dateTime = Carbon::createFromFormat('Y-m-d H:i', $this->option('datetime'));
        } catch (\Exception $e) {
            $this->error('Invalid datetime format. Use Y-m-d H:i (e.g., 2024-01-15 14:30)');
            return;
        }
        
        $this->info("Running rules scheduled for: {$dateTime->format('Y-m-d H:i:s')}");
        
        $executions = $service->validateScheduledRules($dateTime);
        
        if (empty($executions)) {
            $this->comment('No rules were scheduled to run at the specified time.');
            return;
        }
        
        $this->info('Validation completed. ' . count($executions) . ' rule(s) executed for specified time.');
        $this->displayExecutionResults($executions);
    }
    
    /**
     * Handle running specific rules by code
     */
    protected function handleSpecificRules(ComplianceValidationService $service): void
    {
        $requestedRuleCodes = $this->option('rules');
        $allRules = $service->getAllDefaultRules();
        $rules = $this->filterRulesByCode($allRules, $requestedRuleCodes);
        
        if (empty($rules)) {
            $this->error('No matching rules found for the specified codes: ' . implode(', ', $requestedRuleCodes));
            return;
        }
        
        $this->info('Running ' . count($rules) . ' filtered rule(s): ' . implode(', ', $requestedRuleCodes));
        
        $executions = $service->validate($rules);
        $this->info('Validation completed. ' . count($executions) . ' rule(s) executed.');
        $this->displayExecutionResults($executions);
    }
    
    /**
     * Handle running all rules
     */
    protected function handleAllRules(ComplianceValidationService $service): void
    {
        $allRules = $service->getAllDefaultRules();
        $this->info('Running all ' . count($allRules) . ' configured rules.');
        
        $executions = $service->validate($allRules);
        $this->info('Validation completed. ' . count($executions) . ' rule(s) executed.');
        $this->displayExecutionResults($executions);
    }
    
    /**
     * Display execution results
     */
    protected function displayExecutionResults(array $executions): void
    {
        foreach ($executions as $execution) {
            if ($execution->records_failed > 0) {
                $this->warn("• {$execution->rule_code}: {$execution->records_failed} violations found (checked {$execution->records_checked} records)");
            } else {
                $this->line("• {$execution->rule_code}: No violations (checked {$execution->records_checked} records)");
            }
        }
    }
    
    /**
     * Filter rules by their codes
     */
    protected function filterRulesByCode(array $allRules, array $requestedCodes): array
    {
        $filteredRules = [];
        
        foreach ($allRules as $ruleClass) {
            $rule = is_string($ruleClass) ? app($ruleClass) : $ruleClass;

            if (in_array($rule->getCode(), $requestedCodes)) {
                $filteredRules[] = $rule;
            }
        }
        
        return $filteredRules;
    }
}