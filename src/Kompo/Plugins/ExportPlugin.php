<?php

namespace Condoedge\Utils\Kompo\Plugins;

use Condoedge\Utils\Kompo\Plugins\Base\ComponentPlugin;
use Condoedge\Utils\Services\Exports\ComponentToExportableToExcel;
use Illuminate\Support\Facades\Log;
use Kompo\Elements\BaseElement;
use Kompo\Query;

class ExportPlugin extends ComponentPlugin
{
    public function onBoot()
    {
        if (!($this->component instanceof Query) && !method_exists($this->component, 'getExportableInstance')) {
            throw new \Exception('ExportPlugin can only be used with Query components or has getExportableInstance method.');
        }
    }

    public function managableMethods()
    {
        return [
            'isCalledFromExport',
            'exportToExcel',
        ];
    }

    public function exportToExcel()
    {
        $filename = $this->getFilename() . '-' . uniqid() . '.xlsx';

        try {
            \Maatwebsite\Excel\Facades\Excel::store(
                $this->getExportableInstance(),
                $filename,
            );
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['class' => static::class, 'trace' => $e->getTraceAsString(), 'user' => auth()->user()]);

            return _Html('reports.export-failed')->icon('icon-x')->class('text-lg font-semibold p-4');
        }

        $url = \URL::signedRoute('report.download', ['filename' => $filename]);

        return _Rows(
            _Html('utils.reports-export-completed')->icon('icon-check')->class('text-lg font-semibold'),
            _Link('utils.reports-download-file')->outlined()->toggleClass('hidden')->class('mt-4')
                ->href($url),
        )->class('bg-white rounded-lg p-6');
    }

    protected function getExportableInstance()
    {
        $exportableInstance = method_exists($this->component, 'getExportableInstance') ? $this->component->getExportableInstance() : $this->component;

        if ($exportableInstance instanceof BaseElement) {
            return new ComponentToExportableToExcel($exportableInstance);
        }

        return $exportableInstance;
    }

    protected function getFilename()
    {
        if ($filename = $this->getComponentProperty('filename')) {
            return $filename;
        }

        return 'exported-file';
    }

    public function isCalledFromExport($function)
	{
		$call = collect(debug_backtrace())->first(fn($trace) => $trace['function'] === $function);

        if (!$call) return false;

		return str_contains($call['file'], 'ExportableToExcel');
	}
}