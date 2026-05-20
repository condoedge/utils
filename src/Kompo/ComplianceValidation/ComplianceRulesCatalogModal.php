<?php

namespace Condoedge\Utils\Kompo\ComplianceValidation;

use App\Kompo\Common\Modal;
use App\Services\ComplianceRulesCatalog;

class ComplianceRulesCatalogModal extends Modal
{
    public $_Title = 'compliance.rules-catalog.title';
    public $class = 'overflow-y-auto mini-scroll max-w-5xl';

    public function body()
    {
        $byCategory = ComplianceRulesCatalog::byCategory();

        return _Rows(
            _Html('compliance.rules-catalog.person-rules')->class('text-lg font-semibold mb-2'),
            _Rows(
                ...collect($byCategory[ComplianceRulesCatalog::CATEGORY_PERSON])
                    ->map(fn ($r) => $this->ruleCard($r))->all()
            )->class('gap-2'),

            _Html('compliance.rules-catalog.team-rules')->class('text-lg font-semibold mt-6 mb-2'),
            _Rows(
                ...collect($byCategory[ComplianceRulesCatalog::CATEGORY_TEAM])
                    ->map(fn ($r) => $this->ruleCard($r))->all()
            )->class('gap-2'),
        )->class('p-4');
    }

    protected function ruleCard(array $r)
    {
        return _FlexBetween(
            _Rows(
                _Flex(
                    _Link($r['name_key'])
                        ->class('font-semibold text-info hover:underline')
                        ->href('compliance-rule.details', ['rule_code' => $r['code']]),
                    $this->severityBadge($r['severity']),
                )->class('gap-2 items-center'),
                _Html($r['short_desc_key'])->class('text-sm text-gray-600'),
            )->class('flex-1'),
        )->class('card-white-mbsmall p-3 mb-0');
    }

    protected function severityBadge(string $severity)
    {
        return $severity === ComplianceRulesCatalog::SEVERITY_ERROR
            ? _Pill('compliance.rules-catalog.severity-critical')->class('bg-danger text-white')
            : _Pill('compliance.rules-catalog.severity-warning')->class('bg-warning text-white');
    }
}
