# Rule Scheduling System Documentation

## Overview

The Rule Scheduling System allows compliance rules to define when they should run automatically. This extends the compliance validation framework with time-based execution capabilities.

## Key Features

- **Flexible scheduling**: Daily, weekly, monthly, or custom schedules
- **Schedule inheritance**: All rules inherit scheduling by default (daily at 2 AM)
- **Command-line integration**: Rich CLI commands for scheduled execution
- **Schedule inspection**: View and manage rule schedules
- **Event integration**: Works seamlessly with the notification system

## Architecture Components

### 1. ScheduledRuleContract Interface
Defines the contract for scheduled rules:

```php
interface ScheduledRuleContract
{
    public function shouldRunAt(Carbon $dateTime): bool;
    public function getNextRunTime(Carbon $after): ?Carbon;
    public function getScheduleDescription(): string;
    public function getFrequency(): string;
}
```

### 2. Schedule Classes
Pre-built schedule implementations:

- **`DailySchedule`** - Runs daily at specified time
- **`WeeklySchedule`** - Runs weekly on specified day/time  
- **`MonthlySchedule`** - Runs monthly on specified day/time
- **`CustomSchedule`** - Flexible custom scheduling with helper methods

### 3. Enhanced BaseRule
All rules now implement `ScheduledRuleContract` by default:

```php
abstract class BaseRule implements RuleContract, ScheduledRuleContract
{
    protected function createDefaultSchedule(): ScheduleContract
    {
        return new DailySchedule(2, 0); // Daily at 2 AM
    }
}
```

## Creating Scheduled Rules

### Basic Daily Rule
```php
class DailyTeamValidationRule extends DBBaseRule
{
    protected function createDefaultSchedule(): ScheduleContract
    {
        return new DailySchedule(9, 0); // Daily at 9 AM
    }
    
    // ... rest of rule implementation
}
```

### Weekly Rule
```php
class WeeklyPersonAuditRule extends CodeBaseRule
{
    protected function createDefaultSchedule(): ScheduleContract
    {
        // Weekly on Mondays at 6 AM
        return new WeeklySchedule(1, 6, 0);
    }
    
    // ... rest of rule implementation
}
```

### Monthly Rule
```php
class MonthlySecurityAuditRule extends CodeBaseRule
{
    protected function createDefaultSchedule(): ScheduleContract
    {
        // Monthly on 1st day at 3 AM
        return new MonthlySchedule(1, 3, 0);
    }
    
    // ... rest of rule implementation
}
```

### Custom Schedule Rules
```php
class BusinessHoursValidationRule extends CodeBaseRule
{
    protected function createDefaultSchedule(): ScheduleContract
    {
        // Business days only at 9 AM
        return CustomSchedule::businessDays(9, 0);
    }
}

class MidMonthRule extends CodeBaseRule
{
    protected function createDefaultSchedule(): ScheduleContract
    {
        // 1st and 15th of each month at 2 AM
        return CustomSchedule::onDaysOfMonth([1, 15], 2, 0);
    }
}
```

### Advanced Custom Schedule
```php
class HourlyRule extends CodeBaseRule
{
    protected function createDefaultSchedule(): ScheduleContract
    {
        // Every 6 hours at minute 0
        return CustomSchedule::everyHours(6, 0);
    }
}
```

## Command Line Usage

### Basic Scheduled Execution
```bash
# Run rules scheduled for current time
php artisan compliance:run-validation --scheduled

# Run rules scheduled for specific time
php artisan compliance:run-validation --datetime="2024-01-15 14:30"

# Run rules by frequency
php artisan compliance:run-validation --frequency=daily
php artisan compliance:run-validation --frequency=weekly
php artisan compliance:run-validation --frequency=monthly
```

### Schedule Management
```bash
# List all rules with their schedules
php artisan compliance:run-validation --list-schedules

# Show upcoming runs in next 24 hours
php artisan compliance:run-validation --upcoming-runs=24

# Show upcoming runs in next week
php artisan compliance:run-validation --upcoming-runs=168
```

### Traditional Usage (Still Works)
```bash
# Run all rules (ignores schedules)
php artisan compliance:run-validation

# Run specific rules
php artisan compliance:run-validation --rules=team_validation_rule,person_audit_rule
```

## Service Integration

### ComplianceValidationService Methods

```php
// Run scheduled rules
$executions = $service->validateScheduledRules(); // Current time
$executions = $service->validateScheduledRules($dateTime); // Specific time

// Run by frequency
$executions = $service->validateRulesByFrequency('daily');

// Get schedule information
$schedules = $service->getRulesWithSchedules();
$upcomingRuns = $service->getUpcomingRuns($from, $to);
$frequencies = $service->getAvailableFrequencies();
```

### RulesGetter Methods

```php
// Get rules for specific time
$rules = $rulesGetter->getRulesScheduledFor($dateTime);

// Get by frequency
$dailyRules = $rulesGetter->getRulesByFrequency('daily');

// Get schedule info
$schedules = $rulesGetter->getRulesWithSchedules();
$upcoming = $rulesGetter->getRulesWithUpcomingRuns($from, $to);
```

## Scheduler Integration

### Automatic Scheduled Runs
Update your Laravel scheduler to run scheduled rules:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Run scheduled rules every minute (they decide if they should actually run)
    $schedule->command('compliance:run-validation --scheduled')->everyMinute();
    
    // Or run at specific intervals:
    // $schedule->command('compliance:run-validation --frequency=daily')->daily();
    // $schedule->command('compliance:run-validation --frequency=weekly')->weekly();
}
```

### Custom Frequency Scheduling
```php
// Run custom frequencies
$schedule->command('compliance:run-validation --frequency=business-days')->weekdays()->at('09:00');
$schedule->command('compliance:run-validation --frequency=every-6h')->everySixHours();
```

## Advanced Features

### Dynamic Schedule Modification
```php
// Rules can modify their schedule at runtime
$rule = new TeamValidationRule();
$rule->setSchedule(new WeeklySchedule(1, 10, 0)); // Change to weekly
```

### Schedule Inspection
```php
$rule = new MonthlyRule();
$nextRun = $rule->getNextRunTime(now());
$description = $rule->getScheduleDescription();
$frequency = $rule->getFrequency();
```

### Custom Schedule Logic
```php
class CustomRule extends BaseRule 
{
    protected function createDefaultSchedule(): ScheduleContract
    {
        return new CustomSchedule(
            // Should run callback
            function (Carbon $dateTime) {
                return $dateTime->isWeekday() && 
                       $dateTime->hour === 14 && 
                       $dateTime->minute === 0 &&
                       $dateTime->day % 7 === 0; // Every 7th day
            },
            // Next run callback  
            function (Carbon $after) {
                $next = $after->copy();
                while (!$this->shouldRunCallback($next)) {
                    $next->addDay();
                }
                return $next;
            },
            'Every 7th weekday at 2 PM',
            'custom-7th-weekday'
        );
    }
}
```

## Migration Guide

### Existing Rules
All existing rules automatically get daily scheduling at 2 AM. No changes needed unless you want custom scheduling.

### Adding Custom Schedules
1. Override `createDefaultSchedule()` in your rule
2. Return appropriate schedule instance
3. Rules will automatically be included in scheduled runs

### Backward Compatibility
- All existing commands work exactly the same
- New scheduling is opt-in via command flags
- Default behavior unchanged

## Best Practices

### 1. Schedule Frequency Guidelines
- **Daily**: Data validation, basic compliance checks
- **Weekly**: Audits, reports, heavy validations  
- **Monthly**: Security audits, deep analytics
- **Custom**: Business-specific requirements

### 2. Resource Management
- Avoid running heavy rules during business hours
- Stagger rule execution to prevent system overload
- Use appropriate frequencies for rule complexity

### 3. Schedule Design
- Make schedules predictable and consistent
- Document complex custom schedules
- Consider timezone implications

### 4. Monitoring
- Use `--list-schedules` to verify configurations
- Monitor `--upcoming-runs` for scheduling conflicts
- Check execution logs for timing issues

## Example Configurations

### Development Environment
```bash
# Check what would run
php artisan compliance:run-validation --upcoming-runs=24

# Test specific schedule
php artisan compliance:run-validation --datetime="2024-01-15 09:00"
```

### Production Environment  
```bash
# Cron job for scheduled execution
# */5 * * * * php artisan compliance:run-validation --scheduled

# Manual daily run
php artisan compliance:run-validation --frequency=daily

# Emergency specific rule execution
php artisan compliance:run-validation --rules=security_audit_rule
```

This scheduling system provides powerful, flexible rule execution while maintaining backward compatibility and ease of use.