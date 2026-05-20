# Compliance Rules Catalog — driven by real rule instances

**Date:** 2026-05-20
**Status:** Design approved, pending implementation

## Problem

`App\Services\ComplianceRulesCatalog` (in SISC) is a hand-maintained static array
of metadata for 12 rules — code, category, severity, and translation keys. It is
disconnected from the real rule classes, must be edited in lockstep with them,
and can drift (e.g. it lists `EnsureTrainingCompletedOnTime`, which has no rule
class and is not registered).

The catalog should instead be a *projection of the real, registered rule
instances* — the same source of truth validation already uses
(`config('kompo-utils.compliance-validation-rules')` → `RulesGetter`).

## Decisions

- **Category** is determined by the entity a rule validates. Each rule declares
  `getValidatableClass(): string` explicitly.
- The static `ComplianceRulesCatalog` class is **deleted entirely**; the catalog
  UI reads registered rule instances via `complianceRulesService()`.
- Rule-level severity: add `getDefaultIssueType()`; `getIssueType($validatable)`
  defaults to it. Rules with per-validatable severity still override `getIssueType`.
- The catalog "section" methods are **optional** (default `null` in `BaseRule`);
  the UI hides empty sections.
- Metadata methods return **already-translated strings** (`__(...)`), consistent
  with the existing `getName()` convention.
- Type-level entity label: a static `validatableTypeName()` on `ValidatableContract`,
  next to the existing instance-level `validatableDisplayName()`.
- The catalog feature moves into the `utils` package (the whole compliance
  framework already lives there).

## Section 1 — Architecture

Single source of truth: rule classes registered in
`config('kompo-utils.compliance-validation-rules')`, discovered by `RulesGetter`.

**Deletions (SISC):** `app/Services/ComplianceRulesCatalog.php`.

**Moves into `utils`:** `ComplianceRulesCatalogModal` and `ComplianceRuleDetailsPage`
re-namespaced from `App\Kompo\ComplianceRules` to
`Condoedge\Utils\Kompo\ComplianceValidation`, rewritten to consume rule
*instances*. Grouping logic → `RulesGetter`. The `compliance-rule.details` route
→ `ComplianceValidationRouter`.

Consequence: `EnsureTrainingCompletedOnTime` drops out of the catalog until a
real rule class exists and is registered — correct for a real-rule-driven catalog.

## Section 2 — Rule contract: the "Catalog metadata" section

`RuleContract` gains a delimited section:

```php
// ===== Catalog metadata — human-facing rule information =====

/** Model class this rule validates. Drives the catalog category. */
public function getValidatableClass(): string;

/** Rule-level severity; also the default for getIssueType(). */
public function getDefaultIssueType(): ComplianceIssueTypeEnum;

public function getName(): string;                 // already exists

public function getShortDescription(): ?string;    // "what it checks"
public function getWhyItMatters(): ?string;
public function getHowItTriggers(): ?string;
public function getHowToResolve(): ?string;
```

`BaseRule`:
- `getValidatableClass()` — `abstract`.
- `getDefaultIssueType()` — `abstract`.
- `getIssueType(ValidatableContract $v)` — now **concrete**: `return $this->getDefaultIssueType();`.
- `getShortDescription()` / `getWhyItMatters()` / `getHowItTriggers()` /
  `getHowToResolve()` — concrete, `return null;`.

`ValidatableContract`:
```php
public function validatableDisplayName(): string;        // instance (exists)
public static function validatableTypeName(): string;    // type label, e.g. "People"
```

**SISC rule changes (all 12):** add `getValidatableClass()`; rename `getIssueType()`
→ `getDefaultIssueType()`; add the four metadata methods returning
`__('compliance.rule.<slug>.*')` strings; `Person`/`Team` implement
`validatableTypeName()`.

The old `SEVERITY_*` constants disappear; the badge reuses
`ComplianceIssueTypeEnum::label()` / `classes()`.

## Section 3 — Service, UI, routing, filter, i18n

**`RulesGetter`:**
```php
/** Registered rules grouped by validatable class; ERROR-first, then by name. */
public function getRulesByCategory(): array; // [Person::class => [...], Team::class => [...]]
```
`getRuleFromCode()` already serves the details lookup.

**`ComplianceRulesCatalogModal`** (utils namespace): iterates `getRulesByCategory()`,
one section per entity (header `$class::validatableTypeName()`), cards use
`getName()`, `getShortDescription()`, severity badge from `getDefaultIssueType()`,
link by `getCode()`.

**`ComplianceRuleDetailsPage`** (utils namespace): `getRuleFromCode($code)`
(404 if null); renders the four metadata sections, skipping null ones;
open-issue count via `ComplianceIssue::where('rule_code', $code)`.

**Router:** add
`Route::get('compliance-rule/{rule_code}', ComplianceRuleDetailsPage::class)->name('compliance-rule.details')`.

**Validatable-type filter:** `AbstractComplianceIssuesTable::top()` gets
`_Select()->name('validatable_type')`, options = distinct validatable classes
from the catalog labelled via `validatableTypeName()`,
`->default($this->defaultValidatableType())->filter()`. New protected
`defaultValidatableType(): ?string` — `null` in the abstract,
`TeamModel::getClass()` in `TeamComplianceIssuesTable`. `baseQuery()` adds
`->when(request('validatable_type'), fn($q,$t) => $q->where('validatable_type', $t))`.

**i18n:** `compliance.rules-catalog.*` UI-label keys → utils `en.json`/`fr.json`;
`compliance.category.*` and per-rule content keys → SISC lang files.

## Testing

- `RulesGetter::getRulesByCategory()` — grouping + ERROR-first/name ordering.
- `getIssueType()` defaults to `getDefaultIssueType()`.
- Komponent tests: catalog modal, details page, validatable-type filter.
