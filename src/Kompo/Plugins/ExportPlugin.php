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
    public function onBoot()
    {
        if (!($this->component instanceof Query) && !method_exists($this->component, 'getExportableInstance')) {
            throw new \Exception('ExportPlugin can only be used with Query components or has getExportableInstance method.');
        }

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

            throw new \Exception(__('error.error-exporting-excel-file'));
        }

        return $filename;
    }

    // Options of exports
    public function exportToExcelViaEmail()
    {
        if (!request('export_email')) {
            return throwValidationError('export_email', __('translate.email-is-required'));
        }

        $exportableInstance = $this->exportableInstance();

        // Removing closures so it can be serialized
        $component = getPrivateProperty($exportableInstance, 'component');

        if ($component) unsetPrivateProperty($component, 'query');

        cleanClosuresFromObject($exportableInstance);

        SendExportViaEmail::dispatch($exportableInstance, request('export_email'), $this->getFilename() . '-' . uniqid() . '.xlsx');    

        return _Rows(
            _Html('translate.utils.request-for-export-done')->icon('icon-check')->class('text-lg font-semibold'),
            _Html('translate.wait-for-the-email-to-download-your-file'),
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
                _Html('translate.this-report-can-take-some-time-select-the-option')->class('mb-2 text-center text-lg'),

                _Rows($this->sendExportViaEmailEls()),

                _Html('translate.or')->class('my-4 text-center text-lg'),
                
                _Button('translate.direct-export')->class('w-full')
                    ->selfPost('pluginMethod', [
                        'method' => 'directExportToExcel',
                    ])
                    ->inPanel('export-options'),
            )->class('p-6'),
        )->id('export-options');
    }

    protected function sendExportViaEmailEls()
    {
        return [
           _InputEmail('translate.email')->id('export-email-visual')->name('export_email', false)
                ->run('() => {
                    const value = $("#export-email-visual").val();

                    $("#export-email-patch").val(value);

                    $("#export-email-patch").get(0).dispatchEvent(new Event("input"))
                }'),

            _ButtonOutlined('translate.export-via-email')
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
            _Html('utils.reports-export-completed')->icon('icon-check')->class('text-lg font-semibold'),
            _Link('utils.reports-download-file')->outlined()->toggleClass('hidden')->class('mt-4')
                ->href($url),
        )->class('bg-white rounded-lg p-6');
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
    protected function patchToSendEmailValue()
    {
        if (!($this->component instanceof Query)) {
            return;
        }

        $filtersTop = $this->component->filters['top'];
        $emailEl = _Input()->name('export_email', false)->id('export-email-patch')->class('opacity-0 absolute -z-10 bottom-0 left-0');

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