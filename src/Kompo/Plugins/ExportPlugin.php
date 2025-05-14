<?php

namespace Condoedge\Utils\Kompo\Plugins;

use Condoedge\Utils\Jobs\SendExportViaEmail;
use Condoedge\Utils\Kompo\Plugins\Base\ComponentPlugin;
use Condoedge\Utils\Services\Exports\ComponentToExportableToExcel;
use Condoedge\Utils\Services\Exports\ExportableHeaviness;
use Illuminate\Support\Facades\Log;
use Kompo\Elements\BaseElement;
use Kompo\Komponents\KomponentManager;
use Kompo\Query;

class ExportPlugin extends ComponentPlugin
{
    protected $componentId;

    public function onBoot()
    {
        if (!($this->component instanceof Query) && !method_exists($this->component, 'getExportableInstance')) {
            throw new \Exception('ExportPlugin can only be used with Query components or has getExportableInstance method.');
        }

        $this->setComponentId();
        $this->patchToSendEmailValue();
    }

    public function managableMethods()
    {
        return [
            'isCalledFromExport',
            'exportToExcel',
            'exportToExcelRaw',
            'convertThisToExportableInstance',
            'directExportToExcel',
            'exportToExcelViaEmail',
            'exportToExcelViaEmailEl',
        ];
    }

    // Handler of export option
    public function exportToExcel()
    {
        $exportableInstance = $this->exportableInstance();

        $heavinessLevel = getPrivateProperty($exportableInstance, 'heavinessLevel') ?? $this->detectHeaviness($exportableInstance);

        return $this->{$heavinessLevel->functionExportName()}($exportableInstance);
    }

    public function exportToExcelRaw($exportableInstance = null)
    {
        $filename = $this->getFilename() . '-' . uniqid() . '.xlsx';

        try {
            \Maatwebsite\Excel\Facades\Excel::store(
                $exportableInstance ?? $this->exportableInstance(),
                $filename,
            );
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['class' => static::class, 'trace' => $e->getTraceAsString(), 'user' => auth()->user(), 'campaign' => currentCampaign()]);

            throw new \Exception(__('translate.error.error-exporting-excel-file'));
        }

        return $filename;
    }

    // Options of exports
    public function exportToExcelViaEmail()
    {
        if (!request('export_email')) {
            return throwValidationError('export_email', __('utils.email-is-required'));
        }

        $exportableInstance = $this->exportableInstance();

        // Removing closures so it can be serialized
        $component = getPrivateProperty($exportableInstance, 'component');

        if ($component) unsetPrivateProperty($component, 'query');

        cleanClosuresFromObject($exportableInstance);

        SendExportViaEmail::dispatch($exportableInstance, request('export_email'), $this->getFilename() . '-' . uniqid() . '.xlsx');    

        return _Rows(
            _Html('utils.request-for-export-done')->icon('icon-check')->class('text-lg font-semibold'),
            _Html('utils.wait-for-the-email-to-download-your-file'),
        )->class('bg-white rounded-lg p-6');
    }

    public function exportToExcelViaEmailEl()
    {
        return _Panel($this->sendExportViaEmailEls())->id('export-options')->class('p-6');
    }

    public function selectExportOptions()
    {
        return _Panel(
            _Rows(
                _Html('utils.this-report-can-take-some-time-select-the-option')->class('mb-6 text-center text-lg'),
                _CardGray100(
                    _Rows($this->sendExportViaEmailEls()),
                )->class('px-6 py-4'),
                _Html('utils.or')->class('mt-2 mb-6 text-center text-lg'),
                _CardWhite(
                    _Button('utils.direct-export')->class('w-full')
                        ->selfPost('pluginMethod', [
                            'method' => 'directExportToExcel',
                        ])
                        ->inPanel('export-options'),
                )->class('px-6 mb-2'),
            )->class('p-6 max-w-md'),
        )->id('export-options');
    }

    protected function sendExportViaEmailEls()
    {
        $this->setComponentId();
        
        return [
           _InputEmail('utils.export-will-be-sent-to-thi-email')->id('export-email-visual')->name('export_email', false)
                ->run('() => {
                    const value = $("#export-email-visual").val();

                    const patchId = "#export-email-patch" + "' . $this->componentId . '";

                    $(patchId).val(value);

                    $(patchId).get(0).dispatchEvent(new Event("input"))
                }'),

            _ButtonOutlined('utils.export-via-email')
                ->selfPost('pluginMethod', [
                    'method' => 'exportToExcelViaEmail',
                ])
                ->withAllFormValues()
                ->inPanel('export-options'),
        ];
    }

    public function directExportToExcel($exportableInstance = null)
    {
        $filename = '';

        $exportableInstance = $exportableInstance ?? $this->exportableInstance();

        try {
            $filename = $this->exportToExcelRaw($exportableInstance);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['class' => static::class, 'trace' => $e->getTraceAsString(), 'user' => auth()->user()]);

            return _Html($e->getMessage())->icon('icon-x')->class('text-lg font-semibold p-4');
        }

        $url = \URL::signedRoute('report.download', ['filename' => $filename]);

        return _Rows(
            _Html('utils.reports-export-completed')->icon('icon-check')->class('text-lg font-semibold mb-6'),
            _Link('utils.direct-export')->button()->toggleClass('hidden')->class('mt-4')
                ->href($url),
        )->class('text-center bg-white rounded-lg p-6 max-w-md');
    }

    // GETTERS
    public function convertThisToExportableInstance()
    {
        return new ComponentToExportableToExcel($this->component);
    }

    protected function exportableInstance()
    {
        app()->singleton('bootFlag', fn() => false);
        $exportableInstance = $this->componentHasMethod('getExportableInstance') ? $this->callComponentMethod('getExportableInstance') : $this->component;

        if ($exportableInstance instanceof BaseElement) {
            $exportableInstance->bootForAction();
            return new ComponentToExportableToExcel($exportableInstance);
        }
        app()->singleton('bootFlag', fn() => true);

        return $exportableInstance;
    }

    protected function getFilename()
    {
        if ($filename = $this->getComponentProperty('filename')) {
            return $filename;
        }

        return 'exported-file';
    }

    
    protected function detectHeaviness($exportableInstance)
    {
        if ($exportableInstance instanceof ComponentToExportableToExcel) {
            return $exportableInstance->getHeavinessLevel();
        }

        return ExportableHeaviness::MEDIUM;
    }

    public function isCalledFromExport($function)
	{
		$call = collect(debug_backtrace())->first(fn($trace) => $trace['function'] === $function);

        if (!$call) return false;

		return str_contains($call['file'], 'ExportableToExcel');
	}

    // PATCHES
    protected function setComponentId()
    {
        $this->componentId = $this->getComponentProperty('id') ?? $this->getComponentProperty('config')['X-Kompo-Id'] ?? '';
    }

    protected function patchToSendEmailValue()
    {
        if (!($this->component instanceof Query)) {
            return;
        }

        $filtersTop = $this->component->filters['top'];
        $emailEl = _Input()->name('export_email', false)->id('export-email-patch' . $this->componentId)->class('opacity-0 absolute -z-10 bottom-0 left-0');

        if (!$filtersTop || (is_array($filtersTop) && count($filtersTop) == 0)) {
            $this->component->filters['top'] = [
                _Rows($emailEl)
            ];
            return;
        }

        if (is_array($filtersTop)) {
            $this->component->filters['top'] = array_merge($filtersTop, [
                _Rows($emailEl)
            ]);
            return;
        }

        if (property_exists($filtersTop, 'elements')) {
            $filtersTop->elements = array_merge($filtersTop->elements, [$emailEl]);

            $this->component->filters['top'] = $filtersTop;

            return;
        }
    }
}