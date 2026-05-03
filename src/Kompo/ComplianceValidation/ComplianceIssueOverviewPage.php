<?php

namespace Condoedge\Utils\Kompo\ComplianceValidation;

use Carbon\Carbon;
use Condoedge\Utils\Kompo\Common\Form;
use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;

class ComplianceIssueOverviewPage extends Form
{
    public $id = 'compliance-issue-overview-page';
    public $model = ComplianceIssue::class;

    protected $rule;
    protected $validatable;
    protected $lastExecution;
    protected $notificationLog;
    protected $solutionHandler;

    public function created()
    {
        $this->rule = $this->model->getRuleInstance();
        $this->validatable = $this->model->validatable;
        $this->lastExecution = $this->model->lastExecution();
        $this->notificationLog = $this->model->getNotificationLog();
        $this->solutionHandler = $this->rule?->getSolutionHandler($this->model);
    }

    public function render()
    {
        $accent = $this->model->type->accent();
        $detected = $this->model->detected_at ? Carbon::parse($this->model->detected_at) : null;
        $resolved = $this->model->resolved_at ? Carbon::parse($this->model->resolved_at) : null;
        $explanation = $this->validatable ? $this->rule?->getProblemExplanation($this->validatable) : null;
        $solutionComponent = $this->solutionHandler?->getComponent();
        $solutionActions = $this->solutionHandler?->getActions() ?? [];

        return _Rows(
            _BackButton('compliances-issues.list')->class('mb-3'),

            _Rows(
                // Header
                _Rows(
                    _Flex(
                        $this->model->typeBadge(),
                        _Html('compliance.overview.title')
                            ->class('text-xs uppercase tracking-[0.2em] text-' . $accent . ' font-semibold'),
                    )->class('gap-2 items-center mb-2'),

                    _Html($this->rule?->getName())
                        ->class('text-3xl font-bold text-gray-900 leading-tight'),

                    _Html(implode(' › ', $this->model->validatableBreadcrumb()) ?: '-')
                        ->class('text-sm text-gray-600 mt-2'),
                )->class('px-8 py-6 border-b border-gray-200'),

                // Stats: detected / duration / resolved / schedule
                _Flex(
                    $this->statBlock('calendar', 'compliance.overview.detected-on', $this->formatDateTime($detected), 'text-gray-700'),
                    $this->statDivider(),
                    $this->statBlock('timer', $this->model->durationLabel(), $this->model->durationValue(), 'text-' . $accent),
                    $this->statDivider(),
                    $this->statBlock(
                        $resolved ? 'tick-circle' : 'minus-cirlce',
                        'compliance.overview.resolved-on',
                        $resolved ? $this->formatDateTime($resolved) : __('compliance.overview.not-resolved'),
                        'text-gray-700',
                    ),
                    $this->statDivider(),
                    $this->statBlock('calendar-1', 'compliance.overview.schedule', $this->rule?->getScheduleDescription() ?? '-', 'text-gray-700'),
                    !$this->lastExecution ? null : $this->statDivider(),
                    !$this->lastExecution ? null : $this->statBlock('clock', 'compliance.overview.last-run', $this->formatDateTime($this->lastExecution->execution_started_at), 'text-gray-700'),
                )->class('px-8 py-5 border-b border-gray-200 bg-gray-50/50 gap-4'),

                // Problem
                _Rows(
                    $this->sectionHeading('warning-2', 'compliance.overview.problem-section', 'text-danger'),

                    _Html($this->model->getTranslatedDetailMessage())
                        ->class('text-base font-semibold text-gray-900 leading-snug ' . ($explanation ? 'mb-3' : '')),

                    !$explanation ? null : _Html($explanation)
                        ->class('text-sm text-gray-600 leading-relaxed'),
                )->class('px-8 py-6 border-b border-gray-200'),

                // Solution
                _Rows(
                    $this->sectionHeading('lamp-on', 'compliance.overview.solution-section', 'text-positive'),

                    $solutionComponent ?: _Html('compliance.overview.no-solution-component')->class('text-sm text-gray-500'),

                    empty($solutionActions) ? null : _Flex(...$solutionActions)->class('gap-3 mt-5 justify-center flex-wrap'),
                )->class('px-8 py-6 border-b border-gray-200'),

                // Notifications
                _Rows(
                    $this->sectionHeading('notification', 'compliance.overview.notifications-history', 'text-level1'),

                    $this->notificationLog->isEmpty()
                        ? _Html('compliance.overview.no-notifications-yet')->class('text-sm text-gray-500')
                        : _Rows(...$this->notificationLog->map(fn ($row) => $this->notificationRow($row))->all()),
                )->class('px-8 py-6'),
            )->class('bg-white rounded-lg shadow-sm border-l-8 border-' . $accent . ' overflow-hidden'),
        )->class('p-4');
    }

    public function runResolution()
    {
        return $this->solutionHandler?->resolve();
    }

    protected function sectionHeading($icon, $titleKey, $iconClass)
    {
        return _Flex(
            _Sax($icon, 18)->class($iconClass),
            _Html($titleKey)
                ->class('text-xs uppercase tracking-[0.2em] font-semibold text-gray-700'),
        )->class('gap-2 items-center mb-3');
    }

    protected function statBlock($icon, $labelKey, $value, $valueClass)
    {
        return _Flex(
            _Sax($icon, 22)->class('text-gray-400'),
            _Rows(
                _Html($labelKey)->class('text-[11px] uppercase tracking-wider text-gray-500'),
                _Html($value)->class('text-base font-semibold ' . $valueClass),
            )->class('gap-0'),
        )->class('gap-3 items-center flex-1 min-w-[180px]');
    }

    protected function statDivider()
    {
        return _Html()->class('w-px self-stretch bg-gray-200 hidden md:block');
    }

    protected function notificationRow($row)
    {
        $sentAt = $row['sent_at'] ?? null;

        if ($sentAt instanceof \DateTimeInterface) {
            $sentAt = Carbon::instance($sentAt)->format('Y-m-d H:i');
        }

        return _FlexBetween(
            _Flex(
                _Sax($row['channel_icon'] ?? 'notification', 18)->class('text-' . ($row['channel_color'] ?? 'info')),
                _Rows(
                    _Html($row['recipient'] ?? '-')->class('text-sm font-medium text-gray-900'),
                    _Html($sentAt ?? '-')->class('text-xs text-gray-500'),
                )->class('gap-0'),
            )->class('gap-3 items-center'),

            _Pill($row['status'] ?? '-')
                ->class('bg-' . ($row['status_color'] ?? 'gray') . '/15 text-' . ($row['status_color'] ?? 'gray') . ' text-xs font-semibold'),
        )->class('py-3 border-b border-gray-100 last:border-b-0');
    }

    protected function formatDateTime($value)
    {
        if (!$value) {
            return '-';
        }

        return ($value instanceof Carbon ? $value : Carbon::parse($value))->format('Y-m-d H:i');
    }
}
