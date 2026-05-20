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
            collect($byCategory)->map(fn ($rules, $validatableClass) => _Rows(
                _Html($validatableClass::validatableTypeName())
                    ->class('text-lg font-semibold mb-2'),
                _Rows(
                    collect($rules)->map(fn ($rule) => $this->ruleCard($rule))->all()
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
