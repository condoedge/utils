<?php

namespace Condoedge\Utils\Kompo\ComplianceValidation;

use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;
use Condoedge\Utils\Kompo\Common\WhiteTable;
use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssueTypeEnum;

abstract class AbstractComplianceIssuesTable extends WhiteTable
{
    public function top()
    {
        return _Rows(
            _FlexBetween(
                _Html('translate.compliance.compliance-issues')->class('text-2xl font-semibold'),
                _Flex(
                    !safeIsSuperAdmin() ? null : 
                        _ButtonOutlined('translate.compliance.manual-run')->selfPost('runComplianceValidation')->alert('translate.compliance-executed-successfully')->refresh(),
                    !safeIsSuperAdmin() ? null : _ExcelExportButton()->class('!mb-0'),
                )->class('gap-4')
            ),
            _FlexBetween(
                _FlexEnd(
                    _Input()->name('search', false)->placeholder('generic.search')->filter(),
                )->class('gap-3'),
                _FlexEnd(
                    _MultiSelect()->name('rule_code')
                        ->options(complianceRulesService()->getDefaultRulesWithLabels())
                        ->placeholder('translate.compliance.filter-by-rules')
                        ->filter(),
                    _Select()->name('type')
                        ->options(ComplianceIssueTypeEnum::optionsWithLabels())
                        ->placeholder('translate.compliance.filter-by-type')
                        ->filter(),
                    _Toggle('translate.compliance.show-resolved')->name('show_resolved', false)
                        ->filter(),
                )->class('gap-3'),
            )->class('gap-3 mt-2'),
        );
    }

    public function baseQuery()
    {
        return ComplianceIssue::query()
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
            _Th('translate.compliance.detected-at')->sort('detected_at'),
            _Th('translate.compliance.validatable'),
            _Th('translate.compliance.type')->sort('type'),
            _Th('translate.compliance.status'),
            _Th('translate.compliance.detail-message'),
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
                _DropdownLink('translate.compliance.view-details')
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
}