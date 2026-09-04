<?php

namespace Condoedge\Utils\Listeners;

use Condoedge\Utils\Events\MultipleComplianceIssuesDetected;
use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;
use Condoedge\Utils\Services\ComplianceValidation\ComplianceNotificationLogger;
use Condoedge\Utils\Services\ComplianceValidation\ComplianceNotificationService;
use Condoedge\Utils\Services\ComplianceValidation\NotificationStrategyRegistry;
use Condoedge\Utils\Services\ComplianceValidation\Rules\RuleContract;
use Condoedge\Utils\Services\ComplianceValidation\Strategies\NoNotificationStrategy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class HandleBatchComplianceNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationStrategyRegistry $strategyRegistry;
    protected ComplianceNotificationService $notificationService;
    protected ComplianceNotificationLogger $logger;

    /**
     * Create the event listener
     */
    public function __construct(
        NotificationStrategyRegistry $strategyRegistry,
        ComplianceNotificationService $notificationService,
        ComplianceNotificationLogger $logger
    ) {
        $this->strategyRegistry = $strategyRegistry;
        $this->notificationService = $notificationService;
        $this->logger = $logger;
    }

    /**
     * Handle the event
     */
    public function handle(MultipleComplianceIssuesDetected $event): void
    {
        try {
            $ruleCode = $event->ruleCode;

            // Strategy first, rows second: a context nobody is notified for must
            // never pay to hydrate its failing validatables.
            $notifiablesMap = [];
            foreach ($event->getFailingValidatableIdsByType() as $notificationContext => $ids) {
                $strategy = $this->getNotificationStrategy($event, $notificationContext, $ruleCode);

                if ($strategy instanceof NoNotificationStrategy) {
                    continue;
                }

                $contextNotifiables = $strategy->getBatchNotifiables(
                    $event->loadValidatables($notificationContext, $ids),
                    $ruleCode
                );

                // Merge duplicates as we go: one notifiable can fail across contexts.
                foreach ($contextNotifiables as $key => $data) {
                    $notifiablesMap[$key] ??= [
                        'notifiable' => $data['notifiable'],
                        'validatables' => []
                    ];

                    $notifiablesMap[$key]['validatables'] = array_merge(
                        $notifiablesMap[$key]['validatables'],
                        $data['validatables']
                    );
                }
            }

            if (!$notifiablesMap) {
                return;
            }

            $issuesByValidatable = $event->getPersistedIssues()
                ->keyBy(fn (ComplianceIssue $issue) => $issue->validatable_type . ':' . $issue->validatable_id);

            // Send one notification per unique notifiable with all their validatables
            foreach ($notifiablesMap as $notifiableData) {
                $this->sendBatchNotification(
                    $notifiableData['notifiable'],
                    $event,
                    $notifiableData['validatables'],
                    $issuesByValidatable
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to handle batch compliance notifications', [
                'rule_code' => $event->ruleCode,
                'failing_validatables_count' => count($event->failingValidatableIds),
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
            return complianceRulesService()->getRuleFromCode($ruleCode);
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
     * Send batch notification to a specific notifiable with multiple validatables,
     * then write a "dispatched" log row per (issue, notifiable) so the overview
     * can render notification history. Project-side adapters can call the logger
     * again with per-channel detail (sent, failed, ...) once delivery completes.
     */
    protected function sendBatchNotification($notifiable, MultipleComplianceIssuesDetected $event, array $validatables, Collection $issuesByValidatable): void
    {
        $this->notificationService->sendBatchNotification($notifiable, $event, $validatables);

        foreach ($validatables as $validatable) {
            $issue = $issuesByValidatable->get($validatable->getMorphClass() . ':' . $validatable->getKey());

            if (!$issue) {
                continue;
            }

            $this->logger->log(
                issue: $issue,
                notifiable: $notifiable,
                channel: 'compliance-pipeline',
                status: 'dispatched',
                statusColor: 'info',
            );
        }
    }

    /**
     * Handle a job failure (if using queues)
     */
    public function failed(MultipleComplianceIssuesDetected $event, \Throwable $exception): void
    {
        Log::error('Batch compliance notification job failed', [
            'rule_code' => $event->ruleCode,
            'failing_validatables_count' => count($event->failingValidatableIds),
            'error' => $exception->getMessage()
        ]);
    }
}