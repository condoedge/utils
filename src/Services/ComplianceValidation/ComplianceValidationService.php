<?php

namespace Condoedge\Utils\Services\ComplianceValidation;

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
}