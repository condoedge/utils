<?php

namespace Condoedge\Utils\Services\ComplianceValidation\Strategies;

trait BatchNotificationTrait
{
    /**
     * Default implementation for batch notifiables processing
     * Uses the individual getNotifiables method to build the batch result
     */
    public function getBatchNotifiables(array $validatables, string $ruleCode): array
    {
        $notifiablesMap = [];
        
        foreach ($validatables as $validatable) {
            $notifiables = $this->getNotifiables($validatable, $ruleCode);
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
        
        return $notifiablesMap;
    }

    /**
     * Generate a unique key for a notifiable
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
}