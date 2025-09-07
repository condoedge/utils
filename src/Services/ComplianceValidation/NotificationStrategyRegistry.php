<?php

namespace Condoedge\Utils\Services\ComplianceValidation;

use Condoedge\Utils\Services\ComplianceValidation\Strategies\NoNotificationStrategy;

class NotificationStrategyRegistry
{
    protected array $strategies = [];
    
    /**
     * Register a notification strategy for a specific validatable context and rule combination
     */
    public function register(string $validatableContext, string $ruleCode, NotificationStrategyContract $strategy): void
    {
        $key = $this->buildKey($validatableContext, $ruleCode);
        $this->strategies[$key] = $strategy;
    }
    
    /**
     * Get the notification strategy for a validatable context and rule combination
     * Falls back to default strategy for the context, then to no notifications
     */
    public function getStrategy(string $validatableContext, string $ruleCode): NotificationStrategyContract
    {
        // Try specific rule first
        $specificKey = $this->buildKey($validatableContext, $ruleCode);
        if (isset($this->strategies[$specificKey])) {
            return $this->strategies[$specificKey];
        }
        
        // Fall back to default for this context
        return $this->getDefaultStrategy($validatableContext);
    }
    
    /**
     * Build the key for storing/retrieving strategies
     */
    protected function buildKey(string $context, string $rule): string
    {
        return "{$context}.{$rule}";
    }
    
    /**
     * Get the default strategy for a validatable context
     */
    protected function getDefaultStrategy(string $context): NotificationStrategyContract
    {
        $defaultKey = $this->buildKey($context, 'default');
        return $this->strategies[$defaultKey] ?? new NoNotificationStrategy();
    }
    
    /**
     * Get all registered strategies (for debugging/testing)
     */
    public function getAllStrategies(): array
    {
        return $this->strategies;
    }
}