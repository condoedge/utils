<?php

namespace Condoedge\Utils\Listeners;

use Condoedge\Utils\Events\MultipleComplianceIssuesDetected;
use Condoedge\Utils\Services\ComplianceValidation\ComplianceNotificationService;
use Condoedge\Utils\Services\ComplianceValidation\NotificationStrategyRegistry;
use Condoedge\Utils\Services\ComplianceValidation\Rules\RuleContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class HandleBatchComplianceNotifications implements ShouldQueue
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
    public function handle(MultipleComplianceIssuesDetected $event): void
    {
        try {
            $failingValidatables = $event->failingValidatables;
            $ruleCode = $event->ruleCode;

            // Group validatables by notification context to get appropriate strategies
            $groupedByContext = $this->groupValidatablesByContext($failingValidatables);

            foreach ($groupedByContext as $notificationContext => $validatables) {
                // Get strategy - prefer rule-defined, fallback to registry
                $strategy = $this->getNotificationStrategy($event, $notificationContext, $ruleCode);

                // Collect all unique notifiables for this context
                $notifiablesMap = [];
                foreach ($validatables as $validatable) {
                    $notifiables = $strategy->getNotifiables($validatable, $ruleCode);
                    foreach ($notifiables as $notifiable) {
                        $key = $this->getNotifiableKey($notifiable);
                        if (!isset($notifiablesMap[$key])) {
                            $notifiablesMap[$key] = [
                                'notifiable' => $notifiable,
                                'validatables' => []
                            ];
                        }
                        $notifiablesMap[$key]['validatables'][] = $validatable;
                    }
                }

                // Send batch notifications to each unique notifiable
                foreach ($notifiablesMap as $notifiableData) {
                    $this->sendBatchNotification(
                        $notifiableData['notifiable'], 
                        $event, 
                        $notifiableData['validatables']
                    );
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to handle batch compliance notifications', [
                'rule_code' => $event->ruleCode,
                'failing_validatables_count' => count($event->failingValidatables),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw if you want the job to retry, or handle gracefully
            // throw $e;
        }
    }

    /**
     * Group validatables by their notification context (morph class)
     */
    protected function groupValidatablesByContext(array $validatables): array
    {
        $grouped = [];
        foreach ($validatables as $validatable) {
            $context = $validatable->getMorphClass();
            if (!isset($grouped[$context])) {
                $grouped[$context] = [];
            }
            $grouped[$context][] = $validatable;
        }
        return $grouped;
    }

    /**
     * Get the notification strategy for this event
     * Priority: 1. Rule-defined strategy, 2. Registry fallback
     */
    protected function getNotificationStrategy(MultipleComplianceIssuesDetected $event, string $notificationContext, string $ruleCode)
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
            Log::warning('Could not get rule instance for batch notifications', [
                'rule_code' => $ruleCode,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Generate a unique key for a notifiable to avoid duplicates
     */
    protected function getNotifiableKey($notifiable): string
    {
        if (is_object($notifiable) && method_exists($notifiable, 'getKey')) {
            return get_class($notifiable) . ':' . $notifiable->getKey();
        }
        
        if (is_string($notifiable)) {
            return 'string:' . $notifiable;
        }
        
        return 'unknown:' . serialize($notifiable);
    }

    /**
     * Send batch notification to a specific notifiable with multiple validatables
     */
    protected function sendBatchNotification($notifiable, MultipleComplianceIssuesDetected $event, array $validatables): void
    {
        $this->notificationService->sendBatchNotification($notifiable, $event, $validatables);
    }

    /**
     * Handle a job failure (if using queues)
     */
    public function failed(MultipleComplianceIssuesDetected $event, \Throwable $exception): void
    {
        Log::error('Batch compliance notification job failed', [
            'rule_code' => $event->ruleCode,
            'failing_validatables_count' => count($event->failingValidatables),
            'error' => $exception->getMessage()
        ]);
    }
}