<?php

namespace Condoedge\Utils\Kompo\ComplianceValidation;

use App\Services\ComplianceRulesCatalog;
use Condoedge\Utils\Kompo\Common\Form;
use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;

class ComplianceRuleDetailsPage extends Form
{
    public $id = 'compliance-rule-details-page';

    protected $rule;

    public function created()
    {
        $code = $this->prop('rule_code') ?: request()->route('rule_code');
        $this->rule = ComplianceRulesCatalog::find($code);
        if (!$this->rule) {
            abort(404);
        }
    }

    public function render()
    {
        $code = $this->rule['code'];
        $openIssues = ComplianceIssue::where('rule_code', $code)
            ->whereNull('resolved_at')
            ->count();

        return _Rows(
            _Link('compliance.rules-catalog.back')
                ->icon(_Sax('arrow-left', 16))
                ->class('text-gray-600 hover:underline mb-4 inline-flex items-center')
                ->href('compliances-issues.list'),

            _FlexBetween(
                _TitleMain($this->rule['name_key']),
                $this->severityBadge(),
            )->class('mb-4'),

            _MiniStatCard(
                'compliance.rules-catalog.open-issues',
                $openIssues,
                'danger',
                $openIssues > 0 ? 'bg-danger' : 'bg-greenmain'
            )->class('mb-6'),

            $this->section('compliance.rules-catalog.what-it-checks', $this->rule['short_desc_key']),
            $this->section('compliance.rules-catalog.why-matters',    $this->rule['why_matters_key']),
            $this->section('compliance.rules-catalog.how-triggers',   $this->rule['how_triggers_key']),
            $this->section('compliance.rules-catalog.how-resolve',    $this->rule['how_to_resolve_key']),
        )->class('p-6 max-w-4xl mx-auto');
    }

    protected function section(string $titleKey, string $bodyKey)
    {
        return _Rows(
            _Html($titleKey)->class('text-lg font-semibold mt-4 mb-2'),
            _Html($bodyKey)->class('text-base text-gray-700 leading-relaxed'),
        )->class('mb-2');
    }

    protected function severityBadge()
    {
        return $this->rule['severity'] === ComplianceRulesCatalog::SEVERITY_ERROR
            ? _Pill('compliance.rules-catalog.severity-critical')->class('bg-danger text-white text-lg px-4 py-2')
            : _Pill('compliance.rules-catalog.severity-warning')->class('bg-warning text-white text-lg px-4 py-2');
    }
}
