<?php

namespace Condoedge\Utils\Kompo\ComplianceValidation;

use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;
use Condoedge\Utils\Services\ComplianceValidation\ValidationService;
use Condoedge\Utils\Kompo\Common\WhiteTable;

class ComplianceIssuesTable extends WhiteTable
{
    public function top()
    {
        return _Rows(
            _FlexBetween(
                _Html('compliance.compliance-issues')->class('text-2xl font-semibold'),
                _Flex(
                    _ButtonOutlined('compliance.manual-run')->selfPost('runComplianceValidation')->alert('compliance.running'),
                    _ExcelExportButton()->class('!mb-0'),
                )->class('gap-4')
            ),
            _FlexBetween(
                _FlexEnd(
                    _Input()->name('search', false)->placeholder('generic.search')->filter(),
                )->class('gap-3'),
                _FlexEnd(
                    _MultiSelect()->name('rule_codes', false)
                        ->optionsFrom($this->getAvailableRules())
                        ->placeholder('compliance.filter-by-rules')
                        ->filter(),
                    _Toggle()->name('show_resolved', false)
                        ->text('compliance.show-resolved')
                        ->filter(),
                )->class('gap-3'),
            )->class('gap-3 mt-2'),
        );
    }

    public function query()
    {
        return ComplianceIssue::query()
            ->when(!request('show_resolved'), function ($query) {
                $query->whereNull('resolved_at');
            })
            ->when(request('rule_codes'), function ($query) {
                $query->whereIn('rule_code', request('rule_codes'));
            })
            ->when(request('search'), function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->where('rule_code', 'like', '%' . request('search') . '%')
                            ->orWhere('detail_message', 'like', '%' . request('search') . '%')
                            ->orWhereHasMorph('validatable', '*', function ($morphQuery) {
                                // This would need to be customized based on your validatable models
                                // For example, if teams have searchable names:
                                if (method_exists($morphQuery->getModel(), 'search')) {
                                    $morphQuery->search(request('search'));
                                }
                            });
                });
            })
            ->with('validatable')
            ->orderBy('detected_at', 'desc');
    }

    public function headers()
    {
        return [
            _Th('compliance.detected-at')->sort('detected_at'),
            _Th('compliance.validatable'),
            _Th('compliance.rule-code')->sort('rule_code'),
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
            
            _Html($this->getValidatableDisplay($complianceIssue->validatable)),
            
            _Badge($complianceIssue->rule_code)->class('bg-blue-100 text-blue-800'),
            
            _Badge($this->getTypeLabel($complianceIssue->type))
                ->class($this->getTypeClass($complianceIssue->type)),
            
            _Html($complianceIssue->resolved_at ? 
                '<span class="text-green-600">✓ ' . __('compliance.resolved') . '</span>' : 
                '<span class="text-red-600">● ' . __('compliance.active') . '</span>'),
            
            _Html(str_limit($complianceIssue->detail_message, 50)),
            
            _TripleDotsDropdown(
                _DropdownLink('compliance.view-details')
                    ->selfGet('getInfoModal', ['id' => $complianceIssue->id])
                    ->inModal(),
                
                !$complianceIssue->resolved_at ? 
                    _DropdownLink('compliance.individual-check')
                        ->selfPost('runIndividualCheck', ['id' => $complianceIssue->id])
                        ->alert('compliance.checking') : null,
                        
                !$complianceIssue->resolved_at ?
                    _DropdownLink('compliance.mark-resolved')
                        ->selfPost('markResolved', ['id' => $complianceIssue->id])
                        ->confirm('compliance.confirm-mark-resolved') : null,
            ),
        );
    }

    public function getInfoModal($id)
    {
        return new ComplianceIssueInfoModal($id);
    }

    public function runComplianceValidation()
    {
        $validationService = app(ValidationService::class);
        $rules = config('kompo-utils.compliance-validation-rules', []);
        $executions = $validationService->validate($rules);
        
        $this->success(__('compliance.validation-completed', ['count' => count($executions)]));
    }

    public function runIndividualCheck($id)
    {
        $complianceIssue = ComplianceIssue::findOrFail($id);
        
        // Get the rule class from rule_code
        $ruleClass = $this->getRuleClassFromCode($complianceIssue->rule_code);
        
        if (!$ruleClass) {
            $this->error(__('compliance.rule-not-found'));
            return;
        }
        
        $rule = app($ruleClass);
        
        // Use the rule's individualValidationDetails if available
        try {
            $validatable = $complianceIssue->validatable;
            $details = $rule->individualValidationDetails($validatable);
            
            return $details;
        } catch (\Exception $e) {
            // If individual check is not available, run a single validation
            $validationService = app(ValidationService::class);
            $executions = $validationService->validate([$ruleClass]);
            
            $this->success(__('compliance.individual-check-completed'));
        }
    }

    public function markResolved($id)
    {
        $complianceIssue = ComplianceIssue::findOrFail($id);
        $complianceIssue->update(['resolved_at' => now()]);
        
        $this->success(__('compliance.issue-marked-resolved'));
    }

    protected function getAvailableRules()
    {
        $rules = config('kompo-utils.compliance-validation-rules', []);
        $options = [];
        
        foreach ($rules as $ruleClass) {
            if (class_exists($ruleClass)) {
                $rule = app($ruleClass);
                $options[$rule->getCode()] = $rule->getCode();
            }
        }
        
        return $options;
    }

    protected function getValidatableDisplay($validatable)
    {
        if (!$validatable) return __('compliance.deleted-record');
        
        // Try to get a meaningful display name
        if (method_exists($validatable, 'getNameDisplay')) {
            return $validatable->getNameDisplay();
        }
        
        if (isset($validatable->name)) {
            return $validatable->name;
        }
        
        if (isset($validatable->title)) {
            return $validatable->title;
        }
        
        return class_basename($validatable) . ' #' . $validatable->getKey();
    }

    protected function getTypeLabel($type)
    {
        // Assuming ComplianceIssueTypeEnum has these values
        return match($type) {
            1 => __('compliance.warning'),
            2 => __('compliance.error'),
            default => __('compliance.unknown'),
        };
    }

    protected function getTypeClass($type)
    {
        return match($type) {
            1 => 'bg-yellow-100 text-yellow-800', // Warning
            2 => 'bg-red-100 text-red-800',       // Error
            default => 'bg-gray-100 text-gray-800',
        };
    }

    protected function getRuleClassFromCode($ruleCode)
    {
        $rules = config('kompo-utils.compliance-validation-rules', []);
        
        foreach ($rules as $ruleClass) {
            if (class_exists($ruleClass)) {
                $rule = app($ruleClass);
                if ($rule->getCode() === $ruleCode) {
                    return $ruleClass;
                }
            }
        }
        
        return null;
    }
}