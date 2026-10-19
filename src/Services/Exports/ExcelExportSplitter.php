<?php

namespace Condoedge\Utils\Services\Exports;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Stores an export as Excel::store() does. Opt-in (kompo-utils.export-rows-per-file): an export with more rows than
 * that is stored instead as a zip of xlsx parts of at most that many rows, each with the headings, so no single
 * spreadsheet has to fit in memory. Its parts: ComponentToExportableToExcel::onlyPages(), or an ExportPart.
 */
class ExcelExportSplitter
{
    protected ?int $rowsPerFile;

    public function __construct(?int $rowsPerFile = null)
    {
        $this->rowsPerFile = $rowsPerFile ?? config('kompo-utils.export-rows-per-file');
    }

    /**
     * Returns the name stored on the default disk: $filename, or zipName($filename) when split.
     */
    public function store($export, string $filename): string
    {
        $partsCount = $this->partsCount($export);

        if ($partsCount < 2) {
            Excel::store($export, $filename);

            return $filename;
        }

        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('export-parts-');
        $zipPath = $dir . '.zip';
        mkdir($dir);

        try {
            $zip = new \ZipArchive;
            $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            $pagesPerPart = $this->pagesPerPart($export);
            $name = pathinfo($filename, PATHINFO_FILENAME);

            for ($i = 1; $i <= $partsCount; $i++) {
                // The last part runs to the end: rows added since the count aren't lost.
                $part = $this->part($export, ($i - 1) * $pagesPerPart + 1, $i === $partsCount ? null : $i * $pagesPerPart);

                $partPath = $dir . DIRECTORY_SEPARATOR . "{$name}-{$i}-of-{$partsCount}.xlsx";
                file_put_contents($partPath, Excel::raw($part, ExcelWriter::XLSX));
                $zip->addFile($partPath, basename($partPath));

                unset($part);
                gc_collect_cycles();
            }

            $zip->close();

            $stream = fopen($zipPath, 'r');
            Storage::put(static::zipName($filename), $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        } finally {
            array_map('unlink', glob($dir . DIRECTORY_SEPARATOR . '*') ?: []);
            @rmdir($dir);
            @unlink($zipPath);
        }

        return static::zipName($filename);
    }

    public static function zipName(string $filename): string
    {
        return preg_replace('/\.xlsx$/i', '', $filename) . '.zip';
    }

    protected function partsCount($export): int
    {
        $rowsCount = $this->rowsPerFile ? $this->rowsCount($export) : null;

        return $rowsCount ? (int) ceil($rowsCount / ($this->pagesPerPart($export) * $this->chunkSize($export))) : 1;
    }

    // Null: an export that can't be read by pages, stored whole.
    protected function rowsCount($export): ?int
    {
        $query = match (true) {
            $export instanceof ComponentToExportableToExcel => getPrivateProperty($export, 'component')->query(),
            ExportPart::supports($export) => $export->query(),
            default => null,
        };

        return $query instanceof Builder ? $this->queryCount($query) : null;
    }

    // toBase() applies the model's scopes (the rows the export reads); the pagination count handles group by.
    protected function queryCount($query): int
    {
        return (method_exists($query, 'toBase') ? $query->toBase() : $query)->getCountForPagination();
    }

    protected function part($export, int $firstPage, ?int $lastPage)
    {
        return $export instanceof ComponentToExportableToExcel
            ? $export->onlyPages($firstPage, $lastPage)
            : ExportPart::of($export, $firstPage, $lastPage);
    }

    protected function chunkSize($export): int
    {
        return $export instanceof ComponentToExportableToExcel ? $export->chunkSize() : ExportPart::chunkSize($export);
    }

    // Parts are whole pages of the export's chunks.
    protected function pagesPerPart($export): int
    {
        return max(1, intdiv($this->rowsPerFile, $this->chunkSize($export)));
    }
}
