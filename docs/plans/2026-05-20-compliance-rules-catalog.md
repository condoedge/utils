# Compliance Rules Catalog Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace the hardcoded `ComplianceRulesCatalog` static array with a catalog projected from the real, registered compliance-rule instances.

**Architecture:** Each rule gains a "Catalog metadata" section of methods on `RuleContract`/`BaseRule`. The catalog UI (modal + details page) moves into the `utils` package and reads rule instances via `RulesGetter` (`complianceRulesService()`). Category is derived from the entity each rule validates (`getValidatableClass()`); the entity type label comes from a static `validatableTypeName()` on `ValidatableContract`.

**Tech Stack:** PHP 8 / Laravel package (`Condoedge\Utils`), Kompo UI components. Consuming app: SISC (`vendor/condoedge/utils` is a **symlink** to this repo — edits are picked up live).

---

## Important context

- **Two repos.** `utils` = `C:\Users\jkend\Documents\Projects\kompo\utils` (this repo). SISC = `C:\Users\jkend\Documents\Projects\kompo\SISC`. Rule classes live in SISC `app/ComplianceRules/`.
- **No test harness exists** in `utils` (no `phpunit.xml`, no `tests/`). Verification per task is `php -l` syntax lint plus functional checks in the running SISC app. A unit-test harness (orchestra/testbench is in `require-dev`) is **out of scope** here unless explicitly requested.
- **Three WIP files** currently sit untracked in `utils` with wrong `App\` namespaces — they are reworked/deleted by this plan: `src/Services/ComplianceRulesCatalog.php`, `src/Kompo/ComplianceValidation/ComplianceRulesCatalogModal.php`, `src/Kompo/ComplianceValidation/ComplianceRuleDetailsPage.php`.
- **Ordering matters.** Tasks 1-3 make `getValidatableClass()` / `getDefaultIssueType()` abstract — SISC will not boot until Task 11 updates the rule classes. Do Phase 1-4 in order; verify end-to-end after Task 13.
- **DO NOT COMMIT** — leave all changes staged for review.

---

## Phase 1 — utils: contract & base

### Task 1: Add `validatableTypeName()` to `ValidatableContract`

**Files:**
- Modify: `src/Services/ComplianceValidation/ValidatableContract.php`

**Step 1: Add the static method to the interface**

Replace the file body's interface block with:

```php
interface ValidatableContract
{
    public function getFailedValidationObject(): ComplianceIssue;
    public function scopeSearch($query, $term);
    public function validatableDisplayName(): string;

    /**
     * Human-readable, type-level label for this validatable kind
     * (e.g. "People", "Teams"). Used to group/label the rules catalog.
     */
    public static function validatableTypeName(): string;

    // Model-specific methods that ensure it's a model
    public function getKey();
    public function getMorphClass();
    public function save(array $options = []);
    public function delete();
}
```

**Step 2: Lint**

Run: `php -l src/Services/ComplianceValidation/ValidatableContract.php`
Expected: `No syntax errors detected`

> Note: this makes the method required on every implementer (`Person`, `Team`, `TeamRole` in SISC) — handled in Task 10.

---

### Task 2: Add the "Catalog metadata" section to `RuleContract`

**Files:**
- Modify: `src/Services/ComplianceValidation/Rules/RuleContract.php`

**Step 1: Add the import**

After the existing `use` lines, ensure this is present:

```php
use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssueTypeEnum;
```
(It is already imported — confirm, do not duplicate.)

**Step 2: Add the metadata section before the closing brace of the interface**

```php
    // ============================================================
    // Catalog metadata — human-facing rule information
    // ============================================================

    /**
     * Model class this rule validates (e.g. Person::class).
     * Drives the rules-catalog category/grouping.
     */
    public function getValidatableClass(): string;

    /**
     * Rule-level severity. Also the default returned by getIssueType().
     */
    public function getDefaultIssueType(): ComplianceIssueTypeEnum;

    /** One-line summary of what the rule checks. Null = no summary. */
    public function getShortDescription(): ?string;

    /** Why this rule matters (business/compliance angle). */
    public function getWhyItMatters(): ?string;

    /** What conditions cause this rule to flag an issue. */
    public function getHowItTriggers(): ?string;

    /** How an admin resolves a flagged issue. */
    public function getHowToResolve(): ?string;
```

`getName(): string` already exists in the interface — leave it.

**Step 3: Lint**

Run: `php -l src/Services/ComplianceValidation/Rules/RuleContract.php`
Expected: `No syntax errors detected`

---

### Task 3: Implement defaults in `BaseRule`

**Files:**
- Modify: `src/Services/ComplianceValidation/Rules/BaseRule.php`

**Step 1: Replace the abstract `getIssueType` declaration**

Find (around line 46-49):

```php
    /**
     * Get the type/severity of issue for this validatable
     */
    abstract public function getIssueType(ValidatableContract $validatable): ComplianceIssueTypeEnum;
```

Replace with:

```php
    /**
     * Get the type/severity of issue for this validatable.
     * Defaults to the rule-level getDefaultIssueType(); override only
     * when severity genuinely varies per validatable.
     */
    public function getIssueType(ValidatableContract $validatable): ComplianceIssueTypeEnum
    {
        return $this->getDefaultIssueType();
    }
```

**Step 2: Add the new abstract declarations + metadata defaults**

Immediately after the `getIssueType()` method added above, insert:

```php
    /**
     * Model class this rule validates (e.g. Person::class).
     */
    abstract public function getValidatableClass(): string;

    /**
     * Rule-level severity. Also the default for getIssueType().
     */
    abstract public function getDefaultIssueType(): ComplianceIssueTypeEnum;

    public function getShortDescription(): ?string
    {
        return null;
    }

    public function getWhyItMatters(): ?string
    {
        return null;
    }

    public function getHowItTriggers(): ?string
    {
        return null;
    }

    public function getHowToResolve(): ?string
    {
        return null;
    }
```

**Step 3: Lint**

Run: `php -l src/Services/ComplianceValidation/Rules/BaseRule.php`
Expected: `No syntax errors detected`

---

## Phase 2 — utils: catalog service

### Task 4: Add `getRulesByCategory()` to `RulesGetter`

**Files:**
- Modify: `src/Services/ComplianceValidation/RulesGetter.php`

**Step 1: Add the method after `getRuleFromCode()`**

```php
    /**
     * All registered rules grouped by validatable class.
     * Within each group: ERROR before WARNING, then alphabetical by name.
     *
     * @return array<string, RuleContract[]>
     */
    public function getRulesByCategory(): array
    {
        $grouped = [];

        foreach ($this->getAllDefaultRules() as $rule) {
            $grouped[$rule->getValidatableClass()][] = $rule;
        }

        foreach ($grouped as &$rules) {
            usort($rules, function (RuleContract $a, RuleContract $b) {
                // ComplianceIssueTypeEnum: WARNING=1, ERROR=2 -> ERROR first (desc).
                $cmp = $b->getDefaultIssueType()->value <=> $a->getDefaultIssueType()->value;

                return $cmp !== 0 ? $cmp : strcmp($a->getName(), $b->getName());
            });
        }
        unset($rules);

        return $grouped;
    }
```

**Step 2: Lint**

Run: `php -l src/Services/ComplianceValidation/RulesGetter.php`
Expected: `No syntax errors detected`

---

## Phase 3 — utils: UI, routing, i18n

### Task 5: Rewrite `ComplianceRulesCatalogModal`

**Files:**
- Rewrite (overwrite the untracked WIP file): `src/Kompo/ComplianceValidation/ComplianceRulesCatalogModal.php`

**Step 1: Replace the whole file**

```php
<?php

namespace Condoedge\Utils\Kompo\ComplianceValidation;

use Condoedge\Utils\Kompo\Common\Modal;
use Condoedge\Utils\Services\ComplianceValidation\Rules\RuleContract;

class ComplianceRulesCatalogModal extends Modal
{
    public $_Title = 'compliance.rules-catalog.title';
    public $class = 'overflow-y-auto mini-scroll max-w-5xl';

    public function body()
    {
        $byCategory = complianceRulesService()->getRulesByCategory();

        return _Rows(
            ...collect($byCategory)->map(fn ($rules, $validatableClass) => _Rows(
                _Html($validatableClass::validatableTypeName())
                    ->class('text-lg font-semibold mb-2'),
                _Rows(
                    ...collect($rules)->map(fn ($rule) => $this->ruleCard($rule))->all()
                )->class('gap-2'),
            )->class('mb-6'))->all(),
        )->class('p-4');
    }

    protected function ruleCard(RuleContract $rule)
    {
        return _FlexBetween(
            _Rows(
                _Flex(
                    _Link($rule->getName())
                        ->class('font-semibold text-info hover:underline')
                        ->href('compliance-rule.details', ['rule_code' => $rule->getCode()]),
                    $this->severityBadge($rule),
                )->class('gap-2 items-center'),
                !$rule->getShortDescription() ? null :
                    _Html($rule->getShortDescription())->class('text-sm text-gray-600'),
            )->class('flex-1'),
        )->class('card-white-mbsmall p-3 mb-0');
    }

    protected function severityBadge(RuleContract $rule)
    {
        $type = $rule->getDefaultIssueType();

        return _Pill($type->label())->class($type->classes());
    }
}
```

**Step 2: Lint**

Run: `php -l src/Kompo/ComplianceValidation/ComplianceRulesCatalogModal.php`
Expected: `No syntax errors detected`

---

### Task 6: Rewrite `ComplianceRuleDetailsPage`

**Files:**
- Rewrite (overwrite the untracked WIP file): `src/Kompo/ComplianceValidation/ComplianceRuleDetailsPage.php`

**Step 1: Replace the whole file**

```php
<?php

namespace Condoedge\Utils\Kompo\ComplianceValidation;

use Condoedge\Utils\Kompo\Common\Form;
use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;

class ComplianceRuleDetailsPage extends Form
{
    public $id = 'compliance-rule-details-page';

    protected $rule;

    public function created()
    {
        $code = $this->prop('rule_code') ?: request()->route('rule_code');
        $this->rule = complianceRulesService()->getRuleFromCode($code);

        if (!$this->rule) {
            abort(404);
        }
    }

    public function render()
    {
        $openIssues = ComplianceIssue::where('rule_code', $this->rule->getCode())
            ->whereNull('resolved_at')
            ->count();

        return _Rows(
            _Link('compliance.rules-catalog.back')
                ->icon(_Sax('arrow-left', 16))
                ->class('text-gray-600 hover:underline mb-4 inline-flex items-center')
                ->href('compliances-issues.list'),

            _FlexBetween(
                _TitleMain($this->rule->getName()),
                $this->severityBadge(),
            )->class('mb-4'),

            _MiniStatCard(
                'compliance.rules-catalog.open-issues',
                $openIssues,
                'danger',
                $openIssues > 0 ? 'bg-danger' : 'bg-greenmain'
            )->class('mb-6'),

            $this->section('compliance.rules-catalog.what-it-checks', $this->rule->getShortDescription()),
            $this->section('compliance.rules-catalog.why-matters',    $this->rule->getWhyItMatters()),
            $this->section('compliance.rules-catalog.how-triggers',   $this->rule->getHowItTriggers()),
            $this->section('compliance.rules-catalog.how-resolve',    $this->rule->getHowToResolve()),
        )->class('p-6 max-w-4xl mx-auto');
    }

    protected function section(string $titleKey, ?string $body)
    {
        if (!$body) {
            return null;
        }

        return _Rows(
            _Html($titleKey)->class('text-lg font-semibold mt-4 mb-2'),
            _Html($body)->class('text-base text-gray-700 leading-relaxed'),
        )->class('mb-2');
    }

    protected function severityBadge()
    {
        $type = $this->rule->getDefaultIssueType();

        return _Pill($type->label())->class($type->classes() . ' text-lg px-4 py-2');
    }
}
```

**Step 2: Lint**

Run: `php -l src/Kompo/ComplianceValidation/ComplianceRuleDetailsPage.php`
Expected: `No syntax errors detected`

---

### Task 7: Delete the stale `ComplianceRulesCatalog` WIP file

**Files:**
- Delete: `src/Services/ComplianceRulesCatalog.php`

**Step 1: Delete it**

Run: `git rm -f --cached src/Services/ComplianceRulesCatalog.php 2>$null; Remove-Item src/Services/ComplianceRulesCatalog.php -Force`

(The file is untracked WIP with a wrong `App\Services` namespace; it is fully replaced by `RulesGetter::getRulesByCategory()`.)

**Step 2: Confirm nothing references it**

Run (search tool): `search_code_advanced({ "pattern": "ComplianceRulesCatalog", "file_pattern": "*.php" })`
Expected: only matches in this plan / design doc — no `.php` source references.

---

### Task 8: Register the `compliance-rule.details` route

**Files:**
- Modify: `src/Services/ComplianceValidation/ComplianceValidationRouter.php`
- Modify (SISC): `C:\Users\jkend\Documents\Projects\kompo\SISC\routes\web.php` lines 125-126

**Step 1: Add the route in the utils router**

In `setRoutes()`, after the `compliance-issue.overview` route, add:

```php
        Route::get('compliance-rule/{rule_code}', \Condoedge\Utils\Kompo\ComplianceValidation\ComplianceRuleDetailsPage::class)
            ->name('compliance-rule.details');
```

**Step 2: Remove the duplicate/broken route from SISC `web.php`**

Delete these lines (125-126) from SISC `routes/web.php`:

```php
    Route::get('compliance-rule/{rule_code}', App\Kompo\ComplianceRules\ComplianceRuleDetailsPage::class)
        ->name('compliance-rule.details');
```

(They point at a non-existent SISC class. `complianceService()->setRoutes()` on line 123 now registers it.)

**Step 3: Lint**

Run: `php -l src/Services/ComplianceValidation/ComplianceValidationRouter.php`
Expected: `No syntax errors detected`

---

### Task 9: Add the validatable-type filter to the issues tables

**Files:**
- Modify: `src/Kompo/ComplianceValidation/AbstractComplianceIssuesTable.php`
- Modify: `src/Kompo/ComplianceValidation/TeamComplianceIssuesTable.php`

**Step 1: Add filter helpers + select to `AbstractComplianceIssuesTable`**

Add these two protected methods to the class:

```php
    /**
     * Validatable-type select options, derived from the rules catalog.
     * @return array<string, string>  [class => type label]
     */
    protected function validatableTypeOptions(): array
    {
        return collect(complianceRulesService()->getRulesByCategory())
            ->keys()
            ->mapWithKeys(fn ($class) => [$class => $class::validatableTypeName()])
            ->all();
    }

    /**
     * Pre-selected validatable type. Null = no default (all types).
     */
    protected function defaultValidatableType(): ?string
    {
        return null;
    }
```

In `top()`, inside the second `_Rows(...)` column (next to the `type` select), add the validatable-type select:

```php
                    _Select()->name('validatable_type')
                        ->options($this->validatableTypeOptions())
                        ->default($this->defaultValidatableType())
                        ->placeholder('compliance.filter-by-validatable-type')
                        ->filter()
                        ->class('w-full !mb-0'),
```

In `baseQuery()`, add the filter clause to the chain:

```php
            ->when(request('validatable_type'), fn($q, $t) => $q->where('validatable_type', $t))
```

**Step 2: Set the default to Team in `TeamComplianceIssuesTable`**

Add this method to `TeamComplianceIssuesTable`:

```php
    protected function defaultValidatableType(): ?string
    {
        return \Condoedge\Utils\Facades\TeamModel::getClass();
    }
```

**Step 3: Lint**

Run: `php -l src/Kompo/ComplianceValidation/AbstractComplianceIssuesTable.php` and
`php -l src/Kompo/ComplianceValidation/TeamComplianceIssuesTable.php`
Expected: `No syntax errors detected` for both.

---

### Task 10: Add catalog i18n keys to utils lang files

**Files:**
- Modify: `resources/lang/en.json`
- Modify: `resources/lang/fr.json`

**Step 1: Add to `en.json`** (alongside the other `compliance.*` keys)

```json
	"compliance.filter-by-validatable-type": "Filter by entity",
	"compliance.rules-catalog.title": "Compliance rules catalog",
	"compliance.rules-catalog.open": "Rules catalog",
	"compliance.rules-catalog.back": "Back to issues",
	"compliance.rules-catalog.open-issues": "Open issues",
	"compliance.rules-catalog.what-it-checks": "What it checks",
	"compliance.rules-catalog.why-matters": "Why it matters",
	"compliance.rules-catalog.how-triggers": "How it triggers",
	"compliance.rules-catalog.how-resolve": "How to resolve",
```

**Step 2: Add to `fr.json`** (same keys, French)

```json
	"compliance.filter-by-validatable-type": "Filtrer par entité",
	"compliance.rules-catalog.title": "Catalogue des règles de conformité",
	"compliance.rules-catalog.open": "Catalogue des règles",
	"compliance.rules-catalog.back": "Retour aux problèmes",
	"compliance.rules-catalog.open-issues": "Problèmes ouverts",
	"compliance.rules-catalog.what-it-checks": "Ce qui est vérifié",
	"compliance.rules-catalog.why-matters": "Pourquoi c'est important",
	"compliance.rules-catalog.how-triggers": "Comment c'est déclenché",
	"compliance.rules-catalog.how-resolve": "Comment résoudre",
```

**Step 3: Validate JSON**

Run: `php -r "json_decode(file_get_contents('resources/lang/en.json'), true); echo json_last_error_msg();"`
Expected: `No error` (repeat for `fr.json`).

---

## Phase 4 — SISC: rule classes & models

> SISC path: `C:\Users\jkend\Documents\Projects\kompo\SISC`

### Task 11: Implement `validatableTypeName()` on the validatable models

**Files (SISC):**
- Modify: `app/Models/Crm/Person.php`
- Modify: `app/Models/Teams/Team.php`
- Modify: `app/Models/Teams/Roles/TeamRole.php`

**Step 1: Add the static method to each model**

`Person.php`:
```php
    public static function validatableTypeName(): string
    {
        return __('compliance.category.people');
    }
```

`Team.php`:
```php
    public static function validatableTypeName(): string
    {
        return __('compliance.category.teams');
    }
```

`TeamRole.php`:
```php
    public static function validatableTypeName(): string
    {
        return __('compliance.category.team-roles');
    }
```

**Step 2: Lint each**

Run `php -l` on each of the three files. Expected: `No syntax errors detected`.

> All three implement `ValidatableContract`, so all three need the method even though only Person/Team currently have rules.

---

### Task 12: Add the catalog metadata to all 12 SISC rule classes

**Files (SISC):** all 12 files in `app/ComplianceRules/` (see table).

For **each** rule class apply this pattern. Worked example — `EnsureAllPeopleHaveValidAge.php`:

**Step 1: Add the import**

```php
use App\Models\Crm\Person;   // already imported in most rules — do not duplicate
```

**Step 2: Add `getValidatableClass()`**

```php
    public function getValidatableClass(): string
    {
        return Person::class;
    }
```

**Step 3: Rename `getIssueType()` → `getDefaultIssueType()`**

Find:
```php
    public function getIssueType(ValidatableContract $validatable): ComplianceIssueTypeEnum
    {
        return ComplianceIssueTypeEnum::ERROR;
    }
```
Replace with:
```php
    public function getDefaultIssueType(): ComplianceIssueTypeEnum
    {
        return ComplianceIssueTypeEnum::ERROR;
    }
```

> Keep the **same enum value** the rule returns today — this is a pure rename, behavior unchanged. If a rule's `getIssueType()` genuinely varies by validatable, keep `getIssueType()` as an override *and* add a `getDefaultIssueType()` returning the most representative value. (None of the 12 currently vary — all return a constant.)

**Step 4: Add the four metadata methods**

```php
    public function getShortDescription(): ?string
    {
        return __('compliance.rule.valid-age.short');
    }

    public function getWhyItMatters(): ?string
    {
        return __('compliance.rule.valid-age.why');
    }

    public function getHowItTriggers(): ?string
    {
        return __('compliance.rule.valid-age.triggers');
    }

    public function getHowToResolve(): ?string
    {
        return __('compliance.rule.valid-age.resolve');
    }
```

**Per-rule mapping** — substitute `getValidatableClass()`, the metadata key slug, and confirm the severity against the rule's *current* `getIssueType()` return:

| Rule class | `getValidatableClass()` | metadata key slug | severity (verify vs. current) |
|---|---|---|---|
| EnsureAllPeopleHaveValidAge | `Person::class` | `valid-age` | ERROR |
| EnsureBackgroundCheckRule | `Person::class` | `background-check` | WARNING |
| EnsureAllVolunteersHaveValidEmail | `Person::class` | `volunteer-email` | ERROR |
| EnsurePersonValidAddresses | `Person::class` | `person-address` | WARNING |
| EnsureScoutHasJustOneActiveTeam | `Person::class` | `scout-single-team` | ERROR |
| EnsurePersonWithoutRolesOnInactiveTeam | `Person::class` | `no-roles-inactive-team` | ERROR |
| EnsurePeopleRatioRule | `Team::class` | `people-ratio` | WARNING |
| EnsureMaxPersonsPerRoleRule | `Team::class` | `max-per-role` | ERROR |
| EnsureAtLeastOnePerson | `Team::class` | `at-least-one-person` | WARNING |
| EnsureRoleIsAllowedInTeamLevel | `Team::class` | `role-team-level` | ERROR |
| EnsureTeamHasPeopleOfAllRoles | `Team::class` | `all-mandatory-roles` | ERROR |
| EnsureTeamValidAddresses | `Team::class` | `team-address` | WARNING |

`Person` = `App\Models\Crm\Person`, `Team` = `App\Models\Teams\Team`. Add the `use` if not already present.

> The severity column is from the old hardcoded catalog. Treat the rule's actual current `getIssueType()` return as authoritative — if a rule disagrees with the table, keep what the rule returns.

> `getName()` is left untouched. The old `compliance.rule.<slug>.name` keys become unused — harmless; do not change `getName()`.

**Step 5: Lint each rule file**

Run `php -l` on each of the 12 files. Expected: `No syntax errors detected`.

---

### Task 13: Add `compliance.category.*` keys to SISC lang files

**Files (SISC):**
- Modify: `resources/lang/en.json`
- Modify: `resources/lang/fr.json`

**Step 1: Add to `en.json`**

```json
	"compliance.category.people": "People",
	"compliance.category.teams": "Teams",
	"compliance.category.team-roles": "Team roles",
```

**Step 2: Add to `fr.json`**

```json
	"compliance.category.people": "Personnes",
	"compliance.category.teams": "Équipes",
	"compliance.category.team-roles": "Rôles d'équipe",
```

**Step 3: Validate JSON**

Run: `php -r "json_decode(file_get_contents('resources/lang/en.json'), true); echo json_last_error_msg();"` for both files.
Expected: `No error`.

---

## Phase 5 — End-to-end verification

### Task 14: Boot + functional check

**Step 1: Autoload + boot**

In SISC: `composer dump-autoload` then `php artisan route:list --name=compliance`
Expected: `compliance-rule.details`, `compliances-issues.list`, `compliance-issue.overview` all listed; no class-not-found errors.

**Step 2: Catalog modal**

Open the compliance issues page → click the "Rules catalog" button.
Expected: rules grouped under "People" and "Teams" headers, ERROR rules first, severity pills correct, short descriptions shown.

**Step 3: Rule details page**

Click a rule name in the catalog.
Expected: details page loads at `compliance-rule/{code}`, shows open-issue count and the four sections (sections with no content are hidden).

**Step 4: Validatable-type filter**

On `TeamComplianceIssuesTable`: the entity filter defaults to "Teams" and shows only team issues; switching to "People" shows person issues.

**Step 5: Regression check**

Confirm the issues table, issue overview page, and a compliance validation run (`php artisan` compliance command, or the "Manual run" button) still work — `getIssueType()` now delegates to `getDefaultIssueType()`.

**Step 6: Report**

Summarize: files changed in each repo, anything that deviated from the table, and leave everything **uncommitted** for review.

---

## Summary of files touched

**utils:** `ValidatableContract.php`, `Rules/RuleContract.php`, `Rules/BaseRule.php`, `RulesGetter.php`, `ComplianceValidationRouter.php`, `Kompo/.../ComplianceRulesCatalogModal.php` (rewrite), `Kompo/.../ComplianceRuleDetailsPage.php` (rewrite), `Kompo/.../AbstractComplianceIssuesTable.php`, `Kompo/.../TeamComplianceIssuesTable.php`, `resources/lang/{en,fr}.json`; **delete** `src/Services/ComplianceRulesCatalog.php`.

**SISC:** `Models/Crm/Person.php`, `Models/Teams/Team.php`, `Models/Teams/Roles/TeamRole.php`, 12 × `app/ComplianceRules/*.php`, `routes/web.php`, `resources/lang/{en,fr}.json`.
