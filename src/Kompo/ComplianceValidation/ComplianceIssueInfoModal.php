<?php

namespace Condoedge\Utils\Kompo\ComplianceValidation;

use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;
use Condoedge\Utils\Services\ComplianceValidation\ValidationService;
use Condoedge\Utils\Kompo\Common\Modal;

class ComplianceIssueInfoModal extends Modal
{
    protected $complianceIssue;

    public function created()
    {
        $this->complianceIssue = ComplianceIssue::with('validatable')->findOrFail($this->id);
    }

    public function headerTitle()
    {
        return __('compliance.issue-details');
    }

    public function body()
    {
        return _Rows(
            _Card(
                _Rows(
                    $this->renderInfoRow(__('compliance.rule-code'), 
                        _Badge($this->complianceIssue->rule_code)->class('bg-blue-100 text-blue-800')),
                    
                    $this->renderInfoRow(__('compliance.type'), 
                        _Badge($this->getTypeLabel($this->complianceIssue->type))
                            ->class($this->getTypeClass($this->complianceIssue->type))),
                    
                    $this->renderInfoRow(__('compliance.detected-at'), 
                        $this->complianceIssue->detected_at ? 
                            \Carbon\Carbon::parse($this->complianceIssue->detected_at)->format('Y-m-d H:i:s') : '-'),
                    
                    $this->renderInfoRow(__('compliance.resolved-at'), 
                        $this->complianceIssue->resolved_at ? 
                            \Carbon\Carbon::parse($this->complianceIssue->resolved_at)->format('Y-m-d H:i:s') : 
                            _Html('<span class="text-red-600">' . __('compliance.not-resolved') . '</span>')),
                    
                    $this->renderInfoRow(__('compliance.validatable'), 
                        $this->getValidatableDisplay($this->complianceIssue->validatable)),
                        
                    $this->renderInfoRow(__('compliance.validatable-type'), 
                        $this->complianceIssue->validatable_type),
                )->class('space-y-3')
            ),
            
            _Card(
                _Rows(
                    _Html('<h3 class="font-semibold text-lg mb-3">' . __('compliance.issue-description') . '</h3>'),
                    _Html('<div class="bg-gray-50 p-4 rounded-lg">' . e($this->complianceIssue->detail_message) . '</div>'),
                )->class('space-y-3')
            ),
            
            // Individual validation details if available
            $this->renderIndividualValidationDetails(),
            
        )->class('space-y-4');
    }

    public function footerButtons()
    {
        $buttons = [
            _ButtonOutlined(__('generic.close'))->closeModal(),
        ];

        if (!$this->complianceIssue->resolved_at) {
            $buttons[] = _Button(__('compliance.individual-check'))
                ->selfPost('runIndividualCheck')
                ->alert(__('compliance.checking'));
                
            $buttons[] = _Button(__('compliance.mark-resolved'))
                ->selfPost('markResolved')
                ->confirm(__('compliance.confirm-mark-resolved'))
                ->class('bg-green-600 hover:bg-green-700');
        }

        return $buttons;
    }

    public function runIndividualCheck()
    {
        $ruleClass = $this->getRuleClassFromCode($this->complianceIssue->rule_code);
        
        if (!$ruleClass) {
            $this->error(__('compliance.rule-not-found'));
            return;
        }
        
        $rule = app($ruleClass);
        
        try {
            $validatable = $this->complianceIssue->validatable;
            if (!$validatable) {
                $this->error(__('compliance.validatable-not-found'));
                return;
            }
            
            $details = $rule->individualValidationDetails($validatable);
            
            // Return the details component to replace the modal content
            return $details;
            
        } catch (\Exception $e) {
            // If individual check is not available, run a single validation
            $validationService = app(ValidationService::class);
            $executions = $validationService->validate([$ruleClass]);
            
            $this->success(__('compliance.individual-check-completed'));
            $this->closeModal();
        }
    }

    public function markResolved()
    {
        $this->complianceIssue->update(['resolved_at' => now()]);
        
        $this->success(__('compliance.issue-marked-resolved'));
        $this->closeModal();
    }

    protected function renderInfoRow($label, $value)
    {
        return _FlexBetween(
            _Html('<span class="font-medium text-gray-700">' . $label . ':</span>'),
            _Html($value)
        )->class('py-2');
    }

    protected function getValidatableDisplay($validatable)
    {
        if (!$validatable) {
            return '<span class="text-red-600">' . __('compliance.deleted-record') . '</span>';
        }
        
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
        return match($type) {
            1 => __('compliance.warning'),
            2 => __('compliance.error'),
            default => __('compliance.unknown'),
        };
    }

    protected function getTypeClass($type)
    {
        return match($type) {
            1 => 'bg-yellow-100 text-yellow-800',
            2 => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    protected function renderIndividualValidationDetails()
    {
        $ruleClass = $this->getRuleClassFromCode($this->complianceIssue->rule_code);
        
        if (!$ruleClass || !$this->complianceIssue->validatable) {
            return null;
        }
        
        try {
            $rule = app($ruleClass);
            $details = $rule->individualValidationDetails($this->complianceIssue->validatable);
            
            return _Card(
                _Rows(
                    _Html('<h3 class="font-semibold text-lg mb-3">' . __('compliance.detailed-analysis') . '</h3>'),
                    $details,
                )->class('space-y-3')
            );
            
        } catch (\Exception $e) {
            return _Card(
                _Html('<div class="text-gray-500 italic">' . 
                    __('compliance.detailed-analysis-not-available') . 
                    '</div>')
            );
        }
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