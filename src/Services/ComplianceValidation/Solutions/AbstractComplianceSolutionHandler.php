<?php

namespace Condoedge\Utils\Services\ComplianceValidation\Solutions;

use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;
use Condoedge\Utils\Services\ComplianceValidation\Rules\RuleContract;
use Kompo\Elements\Element;

/**
 * Mirrors the AbstractCommunicationHandler / AbstractNotificationButtonHandler shape:
 * a handler is bound to a single issue and exposes the kompo pieces the overview renders.
 *
 * Concrete handlers can be:
 *   - inline-form solutions (e.g. re-input an address)
 *   - redirect solutions (link to another page)
 *   - bulk-action solutions (open a sub-table)
 *   - auto-resolve only (no component, just a revalidate button)
 */
abstract class AbstractComplianceSolutionHandler
{
    protected ComplianceIssue $issue;
    protected ?RuleContract $rule;

    public function __construct(ComplianceIssue $issue)
    {
        $this->issue = $issue;
        $this->rule = $issue->getRuleInstance();
    }

    /**
     * Component rendered inside the "Solution" section. Null hides the slot.
     */
    abstract public function getComponent(): ?Element;

    /**
     * Action buttons shown under the component.
     *
     * - Resolved issues: nothing.
     * - No inline solution component: a default "Revalidate" button that posts
     *   to the form's runResolution() method, which delegates to $this->resolve().
     * - With an inline solution component: nothing by default. The concrete
     *   handler is expected to assign its own action (open a modal, redirect,
     *   etc.) since the solution component itself drives the user flow.
     *
     * @return array<\Kompo\Elements\Element>
     */
    public function getActions(): array
    {
        if ($this->issue->resolved_at) {
            return [];
        }

        if ($this->getComponent()) {
            return [];
        }

        return [
            _Button($this->getResolveActionLabel())
                ->selfPost('runResolution')->alert('compliance.revalidated')->refresh(),
        ];
    }

    /**
     * Execute the resolution flow. Default revalidates the rule and marks the
     * issue resolved on success. Override to mark directly, redirect, queue an
     * approval job, etc.
     */
    public function resolve()
    {
        $validNow = $this->issue->revalidate();

        $alertResponse = $validNow
            ? response()->kompoAlert(null, options: [
                'alertClass' => 'vlAlertSuccess',
                'message' => __('compliance.issue-resolved-on-revalidation'),
            ])
            : response()->kompoAlert(null, options: [
                'alertClass' => 'vlAlertError',
                'message' => __('compliance.issue-not-resolved-on-revalidation'),
            ]);

        return response()->kompoMulti([
            $alertResponse,
            response()->kompoRefresh(),
        ]);
    }

    protected function getResolveActionLabel(): string
    {
        return 'compliance.overview.revalidate-action';
    }
}
