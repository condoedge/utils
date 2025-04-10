<?php

namespace Condoedge\Utils\Kompo\Plugins;

use Condoedge\Utils\Kompo\Plugins\ComponentPlugin;
use Condoedge\Utils\Services\Exports\ExportableToExcel;
use Kompo\Query;

class ExportPlugin extends ComponentPlugin
{
    public function onBoot()
    {
        if (!($this->component instanceof Query)) {
            throw new \Exception('ExportPlugin can only be used with Query components.');
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
            _Html('reports-export-completed')->icon('icon-check')->class('text-lg font-semibold'),
            _Link('reports-download-file')->outlined()->toggleClass('hidden')->class('mt-4')
                ->href($url),
        )->class('bg-white rounded-lg p-6');
    }

    protected function getExportableInstance()
    {
        if (method_exists($this->component, 'getExportableInstance')) {
            return new ExportableToExcel($this->component->getExportableInstance);
        }

        return new ExportableToExcel($this->component);
    }

    protected function getFilename()
    {
        if (property_exists($this->component, 'filename')) {
            return $this->component->filename;
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