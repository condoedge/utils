<?php

namespace Condoedge\Utils\Services\ComplianceValidation;

use Condoedge\Utils\Services\ComplianceValidation\Rules\RuleContract;

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
}