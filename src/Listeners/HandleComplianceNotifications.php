<?php

namespace Condoedge\Utils\Listeners;

use Condoedge\Utils\Events\ComplianceIssueDetected;
use Condoedge\Utils\Services\ComplianceValidation\ComplianceNotificationService;
use Condoedge\Utils\Services\ComplianceValidation\NotificationStrategyRegistry;
use Condoedge\Utils\Services\ComplianceValidation\Rules\RuleContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class HandleComplianceNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationStrategyRegistry $strategyRegistry;
    protected ComplianceNotificationService $notificationService;

    /**
     * Create the event listener
     */
    public function __construct(
        NotificationStrategyRegistry $strategyRegistry,
        ComplianceNotificationService $notificationService
    ) {
        $this->strategyRegistry = $strategyRegistry;
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event
     */
    public function handle(ComplianceIssueDetected $event): void
    {
        try {
            $validatable = $event->validatable;
            $ruleCode = $event->ruleCode;
            $notificationContext = $validatable->getMorphClass();

            // Get strategy - prefer rule-defined, fallback to registry
            $strategy = $this->getNotificationStrategy($event, $notificationContext, $ruleCode);

            // Get who should be notified
            $notifiables = $strategy->getNotifiables($validatable, $ruleCode);

            // Send notifications to each notifiable
            foreach ($notifiables as $notifiable) {
                $this->sendNotification($notifiable, $event);
            }
        } catch (\Exception $e) {
            Log::error('Failed to handle compliance notifications', [
                'rule_code' => $event->ruleCode,
                'validatable_type' => get_class($event->validatable),
                'validatable_id' => $event->validatable->getKey(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw if you want the job to retry, or handle gracefully
            // throw $e;
        }
    }

    /**
     * Get the notification strategy for this event
     * Priority: 1. Rule-defined strategy, 2. Registry fallback
     */
    protected function getNotificationStrategy(ComplianceIssueDetected $event, string $notificationContext, string $ruleCode)
    {
        // First, try to get strategy from the rule itself
        $rule = $this->getRuleInstance($ruleCode);
        
        if ($rule && method_exists($rule, 'getNotificationStrategyFor')) {
            $ruleStrategy = $rule->getNotificationStrategyFor($notificationContext);
            if ($ruleStrategy) {
                return $ruleStrategy;
            }
        }
        
        // Fallback to registry
        return $this->strategyRegistry->getStrategy($notificationContext, $ruleCode);
    }

    /**
     * Get the rule instance for strategy lookup
     */
    protected function getRuleInstance(string $ruleCode): ?RuleContract
    {
        try {
            // Try to get the rule instance from the service
            if (function_exists('complianceRulesService')) {
                return complianceRulesService()->getRuleFromCode($ruleCode);
            }
            
            // Alternative: get from the compliance service
            if (function_exists('complianceService')) {
                $rulesGetter = app(\Condoedge\Utils\Services\ComplianceValidation\RulesGetter::class);
                return $rulesGetter->getRuleFromCode($ruleCode);
            }
            
            return null;
        } catch (\Exception $e) {
            Log::warning('Could not get rule instance for notifications', [
                'rule_code' => $ruleCode,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get the source of the notification strategy for logging
     */
    protected function getStrategySource(ComplianceIssueDetected $event, string $notificationContext): string
    {
        $rule = $this->getRuleInstance($event->ruleCode);
        
        if ($rule && method_exists($rule, 'getNotificationStrategyFor')) {
            $ruleStrategy = $rule->getNotificationStrategyFor($notificationContext);
            if ($ruleStrategy) {
                return 'rule-defined';
            }
        }
        
        return 'registry-fallback';
    }

    /**
     * Send notification to a specific notifiable
     */
    protected function sendNotification($notifiable, ComplianceIssueDetected $event): void
    {
        $this->notificationService->sendSingleNotification($notifiable, $event);
    }

    /**
     * Handle a job failure (if using queues)
     */
    public function failed(ComplianceIssueDetected $event, \Throwable $exception): void
    {
        Log::error('Compliance notification job failed', [
            'rule_code' => $event->ruleCode,
            'validatable_type' => get_class($event->validatable),
            'validatable_id' => $event->validatable->getKey(),
            'error' => $exception->getMessage()
        ]);
    }
}