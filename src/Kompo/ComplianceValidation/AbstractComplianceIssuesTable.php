<?php

namespace Condoedge\Utils\Kompo\ComplianceValidation;

use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;
use Condoedge\Utils\Kompo\Common\WhiteTable;
use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssueTypeEnum;

abstract class AbstractComplianceIssuesTable extends WhiteTable
{
    public function top()
    {
        $errorCount = (clone $this->query())->error()->count();
        $warningCount = (clone $this->query())->warning()->count();

        return _Rows(
            _FlexBetween(
                _Html('compliance.compliance-issues')->class('text-2xl font-semibold'),
                _Flex(
                    _Button()
                        ->icon(_Sax('info-circle', 20))
                        ->title('compliance.rules-catalog.open')
                        ->class('!bg-info text-white')
                        ->selfGet('openRulesCatalog')->inModal(),
                    !safeIsSuperAdmin() ? null : 
                        _ButtonOutlined('compliance.manual-run')->selfPost('runComplianceValidation')->alert('translate.running-compliance-in-background'),
                    !safeIsSuperAdmin() ? null : _ExcelExportButton()->class('!mb-0'),
                )->class('gap-4')
            ),
            _Flex(
                _Rows(
                    _MiniStatCard('compliance.type-error-filter', $errorCount, 'danger', 'bg-danger'),
                    _MultiSelect()->name('rule_code')
                        ->options(complianceRulesService()->getDefaultRulesWithLabels())
                        ->placeholder('compliance.filter-by-rules')
                        ->filter()
                        ->class('!mb-0 w-full'),
                )->class('flex-1 gap-2'),
                _Rows(
                    _MiniStatCard('compliance.type-warning-filter', $warningCount, 'clock', 'bg-warning'),
                    _Select()->name('type')->options(
                        ComplianceIssueTypeEnum::optionsWithLabels(),
                    )->placeholder('compliance.filter-by-type')->filter()->class('w-full !mb-0'),
                )->class('flex-1 gap-2'),
            )->class('gap-4 mt-4 mb-3 items-start'),

            _Input()->name('search', false)->placeholder('generic.search')
                ->filter()
                ->class('w-full'),
        );
    }

    public function baseQuery()
    {
        return ComplianceIssue::query()
            ->has('validatable')
            ->when(request('search'), fn($q, $term) => $q->search($term))
            ->with('validatable')
            ->orderBy('type', 'desc')
            ->orderBy('detected_at', 'desc');
    }

    public function headers()
    {
        return [
            _Th('compliance.type')->sort('type'),
            _Th('compliance.detected-at')->sort('detected_at'),
            _Th('compliance.validatable'),
            _Th('compliance.rule'),
            _Th('compliance.status'),
            // _Th('compliance.detail-message'),
            _Th()->class('w-8'),
        ];
    }

    public function render($complianceIssue)
    {
        return _TableRow(
            $complianceIssue->typeBadge(),

            _Html($complianceIssue->detected_at ? 
                \Carbon\Carbon::parse($complianceIssue->detected_at)->format('Y-m-d H:i') : '-'),
            
            _Html($complianceIssue->validatable->validatableDisplayName()),
            
            _Html($complianceIssue->getRuleInstance()->getName()),
            
            $complianceIssue->statusEl(),
            
            // _Text($complianceIssue->detail_message)->maxChars(50),
            
            _TripleDotsDropdown(
                _DropdownLink('compliance.view-overview')
                    ->href('compliance-issue.overview', ['id' => $complianceIssue->id]),
            ),
        );
    }

    public function runComplianceValidation()
    {
        dispatch(function () {
            complianceService()->validateDefaultRules();
        });
    }

    public function openRulesCatalog()
    {
        return new ComplianceRulesCatalogModal();
    }
}