<?php

namespace Condoedge\Utils\Services\Exports\Traits;

use Illuminate\Support\Facades\Log;

// !DEPRECATED!
/**
 * @deprecated This trait is deprecated and will be removed in future versions. Use the ExportPlugin instead.
 * 
 * @see \Condoedge\Utils\Kompo\Plugins\ExportPlugin
 */
trait ExportableUtilsTrait 
{
    protected function getExportableInstance()
    {
        return $this;
    }

    protected function getFilename()
    {
        return $this->filename ?? 'exported-file';
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

    protected function isCalledFromExport($function)
	{
		$call = collect(debug_backtrace())->first(fn($trace) => $trace['function'] === $function);

        if (!$call) return false;

		return str_contains($call['file'], 'ExportableToExcel');
	}
}