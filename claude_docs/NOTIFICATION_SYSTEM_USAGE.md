# Compliance Notification System Usage Guide

## Overview
The notification system uses the Strategy Pattern to determine who gets notified when compliance violations are detected. This avoids circular dependencies and keeps the code clean and extensible.

## Implementation in Your Models

To use the notification system, your models that implement `ValidatableContract` need to implement the `getMorphClass()` method:

### Example Team Model
```php
<?php

namespace App\Models;

use Condoedge\Utils\Services\ComplianceValidation\ValidatableContract;
use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;

class Team extends Model implements ValidatableContract
{
    // ... your existing model code ...

    /**
     * Get notification context for compliance violations
     */
    public function getMorphClass(): string
    {
        return 'team';
    }

    public function getFailedValidationObject(): ComplianceIssue
    {
        $issue = new ComplianceIssue();
        $issue->setValidatable($this);
        return $issue;
    }

    public function validatableDisplayName(): string
    {
        return $this->name ?? "Team #{$this->id}";
    }

    public function scopeSearch($query, $term)
    {
        return $query->where('name', 'like', '%' . $term . '%');
    }
}
```

### Example Person Model
```php
<?php

namespace App\Models;

use Condoedge\Utils\Services\ComplianceValidation\ValidatableContract;
use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;

class Person extends Model implements ValidatableContract
{
    // ... your existing model code ...

    /**
     * Get notification context for compliance violations
     */
    public function getMorphClass(): string
    {
        return 'person';
    }

    public function getFailedValidationObject(): ComplianceIssue
    {
        $issue = new ComplianceIssue();
        $issue->setValidatable($this);
        return $issue;
    }

    public function validatableDisplayName(): string
    {
        return $this->full_name ?? "Person #{$this->id}";
    }

    public function scopeSearch($query, $term)
    {
        return $query->where('first_name', 'like', '%' . $term . '%')
                     ->orWhere('last_name', 'like', '%' . $term . '%');
    }
}
```

### Example Event Model
```php
<?php

namespace App\Models;

use Condoedge\Utils\Services\ComplianceValidation\ValidatableContract;
use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;

class Event extends Model implements ValidatableContract
{
    // ... your existing model code ...

    /**
     * Get notification context for compliance violations
     */
    public function getMorphClass(): string
    {
        return 'event';
    }

    public function getFailedValidationObject(): ComplianceIssue
    {
        $issue = new ComplianceIssue();
        $issue->setValidatable($this);
        return $issue;
    }

    public function validatableDisplayName(): string
    {
        return $this->title ?? "Event #{$this->id}";
    }

    public function scopeSearch($query, $term)
    {
        return $query->where('title', 'like', '%' . $term . '%');
    }
}
```

## How the System Works

1. **Rule detects violation**: A rule (e.g., `EnsureMaxPersonsPerRoleRule`) finds validatables that violate the rule
2. **Event is dispatched**: `ComplianceIssueDetected` event is fired with the validatable and rule code
3. **Strategy is selected**: System uses `validatable->getMorphClass()` + `rule->getCode()` to find the right strategy
4. **Notifications sent**: Strategy determines who to notify and sends notifications

## Current Strategy Registrations

In `CondoedgeUtilsServiceProvider.php`, the following strategies are registered:

### Team Context
- `team` + `ensure_max_persons_per_role_rule` → `TeamManagerNotificationStrategy`
- `team` + `default` → `TeamManagerNotificationStrategy`

### Person Context
- `person` + `minimum_age_rule` → `HRNotificationStrategy`
- `person` + `default` → `SupervisorNotificationStrategy`

### Event Context
- `event` + `default` → `NoNotificationStrategy`

## Adding Custom Strategies

### 1. Create a Custom Strategy
```php
<?php

namespace App\Services\ComplianceValidation\Strategies;

use Condoedge\Utils\Services\ComplianceValidation\NotificationStrategyContract;
use Condoedge\Utils\Services\ComplianceValidation\ValidatableContract;

class CustomNotificationStrategy implements NotificationStrategyContract
{
    public function getNotifiables(ValidatableContract $validatable, string $ruleCode): array
    {
        // Your custom logic here
        // Return array of users/entities that should be notified
        return [];
    }
}
```

### 2. Register the Strategy
In your `AppServiceProvider` or another service provider:

```php
use Condoedge\Utils\Services\ComplianceValidation\NotificationStrategyRegistry;

public function boot()
{
    $this->app->booted(function () {
        $registry = $this->app->make(NotificationStrategyRegistry::class);
        
        // Register your custom strategy
        $registry->register('team', 'specific_rule_code', new CustomNotificationStrategy());
    });
}
```

## Available Notification Strategies

### `NoNotificationStrategy`
- Sends no notifications
- Use for rules that don't require notifications

### `TeamManagerNotificationStrategy`
- Notifies team managers
- Works with validatables that have `team->manager` or `manager` relationships

### `HRNotificationStrategy`
- Notifies all HR personnel
- Looks for users with 'hr' role using various role systems

### `SupervisorNotificationStrategy`
- Notifies direct supervisors
- Looks for `supervisor`, `manager`, or `team->manager` relationships

## Testing the System

You can test the notification system by:

1. Running compliance validation: `php artisan compliance:run-validation`
2. Creating a failing validatable manually and triggering validation
3. Checking the logs for notification events

## Customizing Notification Delivery

The `HandleComplianceNotifications` listener currently logs notifications. To implement actual delivery, modify the `sendNotification()` method in `src/Listeners/HandleComplianceNotifications.php`:

```php
protected function sendNotification($notifiable, ComplianceIssueDetected $event): void
{
    // Example: Laravel Notifications
    $notifiable->notify(new ComplianceIssueNotification($event->complianceIssue));
    
    // Example: Email
    Mail::to($notifiable->email)->send(new ComplianceIssueMail($event->complianceIssue));
    
    // Example: Slack, SMS, etc.
    // ... your implementation
}
```