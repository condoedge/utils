<?php

namespace Condoedge\Utils\Kompo\ComplianceValidation;

use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;
use Condoedge\Utils\Kompo\Common\WhiteTable;
use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssueTypeEnum;
use Condoedge\Utils\Services\ComplianceValidation\Rules\RuleContract;

class ComplianceIssuesTable extends WhiteTable
{
    public function top()
    {
        return _Rows(
            _FlexBetween(
                _Html('compliance.compliance-issues')->class('text-2xl font-semibold'),
                _Flex(
                    _ButtonOutlined('compliance.manual-run')->selfPost('runComplianceValidation')->alert('compliance.compliance-executed-successfully')->refresh(),
                    _ExcelExportButton()->class('!mb-0'),
                )->class('gap-4')
            ),
            _FlexBetween(
                _Flex(
                    _Input()->name('search', false)->placeholder('generic.search')->filter(),
                )->class('gap-3'),
                _FlexEnd(
                    _MultiSelect()->name('rule_code')
                        ->options(complianceRulesService()->getDefaultRulesWithLabels())
                        ->placeholder('compliance.filter-by-rules')
                        ->filter(),
                    _Select()->name('type')
                        ->options(ComplianceIssueTypeEnum::optionsWithLabels())
                        ->placeholder('compliance.filter-by-type')
                        ->filter(),
                    _Toggle('compliance.show-resolved')->name('show_resolved', false)
                        ->filter()
                        ->class('w-full'),
                )->class('gap-3'),
            )->class('gap-3 mt-2'),
        );
    }

    public function query()
    {
        return ComplianceIssue::query()->forTeam($this->getTeamsIds())
            ->has('validatable')
            ->when(!request('show_resolved'), function ($query) {
                $query->whereNull('resolved_at');
            })
            ->when(request('search'), fn($q, $term) => $q->search($term))
            ->with('validatable')
            ->orderBy('detected_at', 'desc');
    }

    public function headers()
    {
        return [
            _Th('compliance.detected-at')->sort('detected_at'),
            _Th('compliance.validatable'),
            _Th('compliance.type')->sort('type'),
            _Th('compliance.status'),
            _Th('compliance.detail-message'),
            _Th()->class('w-8'),
        ];
    }

    public function render($complianceIssue)
    {
        return _TableRow(
            _Html($complianceIssue->detected_at ? 
                \Carbon\Carbon::parse($complianceIssue->detected_at)->format('Y-m-d H:i') : '-'),
            
            _Html($complianceIssue->validatable->validatableDisplayName()),
            
            $complianceIssue->typeBadge(),
            
            $complianceIssue->statusEl(),
            
            _Text($complianceIssue->detail_message)->maxChars(50),
            
            _TripleDotsDropdown(
                _DropdownLink('compliance.view-details')
                    ->selfGet('getInfoModal', ['id' => $complianceIssue->id])
                    ->inModal(),
            ),
        );
    }

    public function getInfoModal($id)
    {
        return new ComplianceIssueInfoModal($id);
    }

    public function runComplianceValidation()
    {
        complianceService()->validateDefaultRules();
    }

    protected function getTeamsIds()
    {
        return safeGetAllTeamChildrenIds();
    }
}