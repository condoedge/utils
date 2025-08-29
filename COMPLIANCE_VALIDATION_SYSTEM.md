# Compliance Validation System

## Overview

The Compliance Validation System is designed to automatically detect and track business rule violations across your application. It follows a simple principle: **rules detect problems, the system handles the rest**.

## Why This Design?

### Core Philosophy
- **Rules are pure detection logic** - they only answer "what's wrong?"
- **System handles orchestration** - persistence, execution tracking, and UI
- **Flexibility first** - works with any entity type (teams, users, events, etc.)
- **Developer-friendly** - creating new rules is straightforward

### Key Design Decisions

**1. Polymorphic Validatable Relationship**
```php
// ComplianceIssue belongs to any model
$issue->validatable; // Could be Team, User, Event, etc.
```
*Why?* Different rules check different entity types. A team role rule checks teams, while a user age rule checks users.

**2. Repository Pattern for Persistence**
```php
// ValidationService orchestrates, Repository handles database
$this->repository->syncIssues($ruleCode, $issuesData, $failingValidatables);
```
*Why?* Rules focus on business logic, not database operations. Makes testing easier and code cleaner.

**3. Rule Codes Instead of Class Names**
```php
// EnsureMaxPersonsPerRoleRule → ensure_max_persons_per_role_rule
public function getCode(): string {
    return Str::snake(class_basename(static::class));
}
```
*Why?* Human-readable identifiers for commands, filtering, and database storage. Easier than full class paths.

## System Components

### 1. Rules (Business Logic)
Rules detect violations. They extend `BaseRule` and implement three key methods:

```php
class EnsureMaxPersonsPerRoleRule extends DBBaseRule
{
    // What is this rule called?
    public function getName(): string {
        return __('compliance.exceeding-maximum-persons-per-role');
    }
    
    // Which entities violate this rule?
    protected function getFailingValidatables(): array {
        return $this->getFailingValidatablesQuery()->get()->all();
    }
    
    // Why did this specific entity fail?
    public function getIssueDescription(ValidatableContract $validatable): string {
        return __('compliance.with-values-all-the-following-roles-exceed', [
            'roles' => $validatable->violating_roles,
        ]);
    }
    
    // How serious is this issue?
    public function getIssueType(ValidatableContract $validatable): ComplianceIssueTypeEnum {
        return ComplianceIssueTypeEnum::ERROR;
    }
}
```

**Two Rule Types Available:**

**DBBaseRule** - For database-driven validations
- Override `getFailingValidatables()` with a query
- System handles the database work
- Best for: role limits, data integrity checks, business constraints

**CodeBaseRule** - For programmatic validations  
- Override `getValidatables()` and `validate($validatable)`
- Check each entity individually
- Best for: complex business logic, API validations, calculated rules

### 2. ValidationService (Orchestrator)
Handles the entire validation workflow:

```php
public function validate(array $rules): array {
    foreach ($rules as $rule) {
        // 1. Find violations
        [$failingValidatables, $testedCount] = $rule->findViolations();
        
        // 2. Create issue records
        $issuesData = $this->createComplianceIssuesData($rule, $failingValidatables);
        
        // 3. Sync with database (insert new, resolve fixed)
        $this->repository->syncIssues($rule->getCode(), $issuesData, $failingValidatables);
        
        // 4. Track execution
        $executions[] = $this->createExecutionRecord($rule, ...);
    }
}
```

### 3. ComplianceIssue (Data Model)
Stores individual violations:
- **validatable** (polymorphic) - The entity that failed
- **rule_code** - Which rule detected this
- **detected_at** - When was this found
- **resolved_at** - When was this fixed (nullable)
- **type** - Warning or Error severity
- **detail_message** - Human-readable explanation

### 4. Individual Revalidation System
Rules can check if specific issues are still valid:

```php
public function individualRevalidate(ComplianceIssue $issue): bool {
    // Re-run the query for just this entity
    return $this->getFailingValidatablesQuery()
        ->where('teams.id', $issue->validatable_id)
        ->doesntExist(); // Returns true if issue is resolved
}
```

**Smart Modal Behavior:**
```php
public function body() {
    $resolved = $this->model->revalidate(); // Calls rule's individualRevalidate()
    
    if ($resolved) {
        return "✅ Issue resolved!" + close button;
    }
    
    return detailed_info + action_buttons;
}
```

### 5. Detailed Analysis Components
Rules can provide rich details for specific issues:

```php
public function individualValidationDetailsComponent(ComplianceIssue $issue): ?BaseElement {
    $team = $issue->validatable;
    
    // Return a custom table showing which roles are over the limit
    return new ExceedingTeamRolesAssignmentsTable([
        'team_id' => $team->id
    ]);
}
```

## Usage Examples

### Running Validations

```bash
# Run all rules
php artisan compliance:run-validation

# Run specific rules
php artisan compliance:run-validation --rules=ensure_max_persons_per_role_rule

# Run multiple specific rules
php artisan compliance:run-validation --rules=user_age_validation_rule,email_compliance_check
```

### Viewing Issues
The `ComplianceIssuesTable` provides:
- **Filtering** by rule, type, status, search
- **Smart revalidation** on modal open
- **Detailed analysis** when rules provide it
- **Manual resolution** for special cases

### Creating New Rules

**1. Simple Database Rule:**
```php
class MyCustomRule extends DBBaseRule {
    protected function getFailingValidatables(): array {
        return User::where('age', '<', 18)->get()->all();
    }
    
    public function getIssueDescription(ValidatableContract $user): string {
        return "User {$user->name} is under 18";
    }
}
```

**2. Complex Logic Rule:**
```php
class MyComplexRule extends CodeBaseRule {
    protected function getValidatables(): array {
        return Event::active()->get()->all();
    }
    
    protected function validate(ValidatableContract $event): bool {
        // Custom validation logic
        return $event->capacity > $event->registered_count * 1.2;
    }
}
```

## Data Flow

```
1. Command/Service calls ValidationService->validate($rules)
2. For each rule:
   - Rule finds failing validatables
   - Service creates issue data
   - Repository syncs with database (insert new, resolve fixed)  
   - Service tracks execution
3. UI displays issues with smart revalidation and details
```

## Benefits of This Design

**For Developers:**
- New rules require just 3-4 methods
- No database knowledge needed
- Testing is straightforward (mock the rule)
- Clear separation of concerns

**For Users:**
- Automatic issue tracking
- Smart revalidation (issues disappear when fixed)
- Rich detail views when available  
- Command-line tools for automation

**For Maintenance:**
- Database changes isolated to repository
- Rule changes don't affect persistence
- Easy to add new entity types
- Clear, predictable structure

## Configuration

Add your rules to `config/kompo-utils.php`:
```php
'compliance-validation-rules' => [
    App\ComplianceRules\EnsureMaxPersonsPerRoleRule::class,
    App\ComplianceRules\UserAgeValidationRule::class,
    // ... more rules
],
```

The system handles everything else automatically.