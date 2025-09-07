# Rule-Based Notification Strategies

## Overview

Rules can now define their own notification strategies directly, eliminating the need for external configuration and `if()` statements. Each rule controls who gets notified when violations are detected.

## Key Features

- **No external configuration**: Strategies defined directly in rules
- **No conditional logic**: Clean mapping without `if()` statements  
- **Context-aware**: Different strategies per validatable context
- **Flexible**: From simple mappings to complex custom logic
- **Fallback support**: Registry-based fallback still available

## How It Works

### 1. Rule-Defined Strategy Priority
```php
// Priority order for notification strategies:
// 1. Rule-defined strategies (getNotificationStrategies)
// 2. Registry fallback (ServiceProvider configuration)
// 3. Default (NoNotificationStrategy)
```

### 2. BaseRule Implementation
Every rule inherits notification capabilities:

```php
abstract class BaseRule implements RuleContract, ScheduledRuleContract
{
    protected function getNotificationStrategies(): array
    {
        // Override this method to define custom strategies
        return [
            'default' => new NoNotificationStrategy()
        ];
    }
    
    public function getNotificationStrategyFor(string $validatableContext): ?NotificationStrategyContract
    {
        // Automatically selects the right strategy
    }
}
```

## Basic Usage

### Simple Strategy Mapping
```php
class TeamValidationRule extends DBBaseRule
{
    protected function getNotificationStrategies(): array
    {
        return [
            'team' => new TeamManagerNotificationStrategy(),
            'person' => new HRNotificationStrategy(),
            'event' => new NoNotificationStrategy(),
            'default' => new TeamManagerNotificationStrategy()
        ];
    }
    
    // ... rest of rule implementation
}
```

### No Configuration Needed!
```php
// ❌ Old way - external configuration in ServiceProvider
$registry->register('team', 'team_validation_rule', new TeamManagerNotificationStrategy());

// ✅ New way - rule defines its own strategies
class TeamValidationRule extends DBBaseRule
{
    protected function getNotificationStrategies(): array
    {
        return [
            'team' => new TeamManagerNotificationStrategy()
        ];
    }
}
```

## Advanced Examples

### 1. Context-Specific Strategies
```php
class SecurityAuditRule extends CodeBaseRule
{
    protected function getNotificationStrategies(): array
    {
        return [
            // Person security issues → HR + Supervisor
            'person' => new class implements NotificationStrategyContract {
                public function getNotifiables(ValidatableContract $validatable, string $ruleCode): array
                {
                    $notifiables = [];
                    
                    // Add HR
                    $hrStrategy = new HRNotificationStrategy();
                    $notifiables = array_merge($notifiables, $hrStrategy->getNotifiables($validatable, $ruleCode));
                    
                    // Add supervisor
                    $supervisorStrategy = new SupervisorNotificationStrategy();
                    $notifiables = array_merge($notifiables, $supervisorStrategy->getNotifiables($validatable, $ruleCode));
                    
                    return array_unique($notifiables, SORT_REGULAR);
                }
            },
            
            // Team security issues → Team Manager only
            'team' => new TeamManagerNotificationStrategy(),
            
            // Everything else → Security Admin
            'default' => new class implements NotificationStrategyContract {
                public function getNotifiables(ValidatableContract $validatable, string $ruleCode): array
                {
                    $securityAdmin = \App\Models\User::where('role', 'security_admin')->first();
                    return $securityAdmin ? [$securityAdmin] : [];
                }
            }
        ];
    }
}
```

### 2. Business Logic-Based Notifications
```php
class CustomBusinessRule extends CodeBaseRule
{
    protected function getNotificationStrategies(): array
    {
        return [
            'person' => new class implements NotificationStrategyContract {
                public function getNotifiables(ValidatableContract $person, string $ruleCode): array
                {
                    // Different notifications based on person's attributes
                    if ($person->department === 'executive') {
                        // Executives → Board members
                        return \App\Models\User::role('board_member')->get()->toArray();
                    } elseif ($person->seniority_level === 'senior') {
                        // Senior staff → Department head + HR
                        $notifiables = [];
                        if ($person->department_head) {
                            $notifiables[] = $person->department_head;
                        }
                        $notifiables = array_merge($notifiables, \App\Models\User::role('hr')->get()->toArray());
                        return $notifiables;
                    } else {
                        // Regular staff → Direct manager
                        return $person->manager ? [$person->manager] : [];
                    }
                }
            },
            
            'team' => new class implements NotificationStrategyContract {
                public function getNotifiables(ValidatableContract $team, string $ruleCode): array
                {
                    // Different notifications based on team attributes
                    if ($team->type === 'project' && $team->budget > 100000) {
                        // High-budget projects → Project committee
                        return \App\Models\User::role('project_committee')->get()->toArray();
                    } elseif ($team->type === 'operational') {
                        // Operational teams → Operations director
                        $opsDirector = \App\Models\User::role('operations_director')->first();
                        return $opsDirector ? [$opsDirector] : [];
                    } else {
                        // Regular teams → Team lead + Department head
                        $notifiables = [];
                        if ($team->team_lead) $notifiables[] = $team->team_lead;
                        if ($team->department?.head) $notifiables[] = $team->department->head;
                        return $notifiables;
                    }
                }
            }
        ];
    }
}
```

### 3. Mixed Strategy Approaches
```php
class FlexibleRule extends CodeBaseRule
{
    protected function getNotificationStrategies(): array
    {
        return [
            // Use existing strategy classes
            'team' => new TeamManagerNotificationStrategy(),
            'person' => new HRNotificationStrategy(),
            
            // Inline custom logic for specific cases
            'event' => new class implements NotificationStrategyContract {
                public function getNotifiables(ValidatableContract $event, string $ruleCode): array
                {
                    if ($event->is_public && $event->expected_attendees > 500) {
                        // Large public events → PR team + Safety officer
                        $notifiables = \App\Models\User::role('pr_team')->get()->toArray();
                        $safetyOfficer = \App\Models\User::role('safety_officer')->first();
                        if ($safetyOfficer) $notifiables[] = $safetyOfficer;
                        return $notifiables;
                    } else {
                        // Regular events → Event organizers
                        return $event->organizers()->get()->toArray();
                    }
                }
            },
            
            // Fallback to existing strategy
            'default' => new SupervisorNotificationStrategy()
        ];
    }
}
```

## Migration Strategies

### Option 1: Gradual Migration
Keep existing registry configuration and add rule-based strategies:

```php
// Existing rules automatically get registry-based notifications
class ExistingRule extends BaseRule
{
    // No getNotificationStrategies() method = uses registry
}

// New rules define their own strategies
class NewRule extends BaseRule
{
    protected function getNotificationStrategies(): array
    {
        return [
            'team' => new TeamManagerNotificationStrategy()
        ];
    }
}
```

### Option 2: Complete Migration
Move all notification logic to rules:

```php
// 1. Add strategies to all rules
class AllRules extends BaseRule
{
    protected function getNotificationStrategies(): array
    {
        return [/* strategies */];
    }
}

// 2. Remove registry configuration from ServiceProvider
// (Registry still works as fallback for missed cases)
```

## Benefits Over Registry Approach

### ❌ Registry Approach (Old)
```php
// ServiceProvider - scattered configuration
$registry->register('team', 'rule_a', new TeamManagerNotificationStrategy());
$registry->register('team', 'rule_b', new HRNotificationStrategy());
$registry->register('person', 'rule_a', new SupervisorNotificationStrategy());

// Rule - no knowledge of notifications
class RuleA extends BaseRule
{
    // Rule logic only, no notification control
}
```

### ✅ Rule-Based Approach (New)
```php
// Rule - complete ownership of its behavior
class RuleA extends BaseRule
{
    protected function getNotificationStrategies(): array
    {
        return [
            'team' => new TeamManagerNotificationStrategy(),
            'person' => new SupervisorNotificationStrategy()
        ];
    }
    
    // Rule logic + notification control in one place
}
```

## Inspection and Debugging

### Check Rule Notification Capabilities
```php
$rule = new MyCustomRule();

// Check if rule has strategies
$hasStrategies = $rule->hasNotificationStrategies(); // bool

// Get supported contexts  
$contexts = $rule->getMorphClass(); // ['team', 'person', 'default']

// Get specific strategy
$teamStrategy = $rule->getNotificationStrategyFor('team');
$defaultStrategy = $rule->getNotificationStrategyFor('unknown'); // falls back to 'default'
```

### Logging Shows Strategy Source
```php
// Log output shows where strategy came from:
[2024-01-15 09:30:45] Compliance notifications sent
    rule_code: team_validation_rule
    validatable_type: App\Models\Team  
    notification_context: team
    notifiables_count: 1
    strategy_source: rule-defined  // ← Shows it came from rule, not registry
```

## Best Practices

### 1. Keep Strategies Close to Logic
```php
class BusinessRule extends BaseRule
{
    protected function getNotificationStrategies(): array
    {
        // Define strategies right where the business logic is
        return [
            'team' => new TeamManagerNotificationStrategy(),
            'person' => new HRNotificationStrategy()
        ];
    }
    
    // Business validation logic here
    protected function validate(ValidatableContract $validatable): bool
    {
        // Rule logic that makes sense with notification strategy above
    }
}
```

### 2. Document Complex Strategies
```php
protected function getNotificationStrategies(): array
{
    return [
        // High-priority security violations go to multiple channels
        'person' => new class implements NotificationStrategyContract {
            /**
             * Security violations for persons notify:
             * 1. HR team (for policy violations)
             * 2. Direct supervisor (for immediate action)
             * 3. Security team (for audit trail)
             */
            public function getNotifiables(ValidatableContract $person, string $ruleCode): array
            {
                // Implementation...
            }
        }
    ];
}
```

### 3. Reuse Strategy Classes
```php
class CommonNotificationStrategies
{
    public static function executiveEscalation(): NotificationStrategyContract
    {
        return new class implements NotificationStrategyContract {
            public function getNotifiables(ValidatableContract $validatable, string $ruleCode): array
            {
                return \App\Models\User::role(['ceo', 'cto', 'compliance_officer'])->get()->toArray();
            }
        };
    }
}

class HighPriorityRule extends BaseRule
{
    protected function getNotificationStrategies(): array
    {
        return [
            'default' => CommonNotificationStrategies::executiveEscalation()
        ];
    }
}
```

## Conclusion

Rule-based notification strategies provide:
- **Better cohesion**: Notification logic lives with rule logic
- **Cleaner code**: No external configuration files to maintain
- **More flexibility**: Complex business logic directly in strategies
- **Easier testing**: Test rule + notifications together
- **Better debugging**: Clear source of notification decisions

The registry approach is still available as a fallback, making migration completely optional and gradual.