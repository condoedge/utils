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
