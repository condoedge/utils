<?php

namespace Condoedge\Utils\Command;

use Condoedge\Utils\Services\ComplianceValidation\ComplianceValidationService;
use Illuminate\Console\Command;

class RunComplianceValidationCommand extends Command
{
    protected $signature = 'compliance:run-validation {--rules=* : Specific rule codes to run (optional)}';

    protected $description = 'Run compliance validation rules. Optionally filter by specific rule codes.';

    public function handle()
    {
        $allRules = complianceService()->getAllDefaultRules();
        $requestedRuleCodes = $this->option('rules');
        
        // Filter rules if specific codes were requested
        if (!empty($requestedRuleCodes)) {
            $rules = $this->filterRulesByCode($allRules, $requestedRuleCodes);
            
            if (empty($rules)) {
                $this->error('No matching rules found for the specified codes: ' . implode(', ', $requestedRuleCodes));
                return;
            }
            
            $this->info('Running ' . count($rules) . ' filtered rule(s): ' . implode(', ', $requestedRuleCodes));
        } else {
            $rules = $allRules;
            $this->info('Running all ' . count($rules) . ' configured rules.');
        }

        $executions = complianceService()->validate($rules);
        
        $this->info('Validation completed. ' . count($executions) . ' rule(s) executed.');
    }
    
    /**
     * Filter rules by their codes
     */
    protected function filterRulesByCode(array $allRules, array $requestedCodes): array
    {
        $filteredRules = [];
        
        foreach ($allRules as $ruleClass) {
            $rule = app($ruleClass);
            if (in_array($rule->getCode(), $requestedCodes)) {
                $filteredRules[] = $ruleClass;
            }
        }
        
        return $filteredRules;
    }
}