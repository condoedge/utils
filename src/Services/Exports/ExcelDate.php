<?php

namespace Condoedge\Utils\Services\Exports;

use Carbon\CarbonInterface;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Value-object wrapper signaling that a cell value is a date. When returned
 * from a Maatwebsite WithMapping `map()` row, the export class should also
 * declare the corresponding column letter in its `columnFormats()` map (e.g.
 * `'F' => NumberFormat::FORMAT_DATE_YYYYMMDD`). The string form falls back
 * to ISO 'Y-m-d' so legacy text-based renderers degrade gracefully.
 */
final class ExcelDate extends AbstractExportType
{
    public function __construct(
        public readonly ?CarbonInterface $value
    ) {}

    public function __toString(): string
    {
        return $this->value?->format('Y-m-d') ?? '';

    }

    public function getFormat(): string
    {
        return NumberFormat::FORMAT_DATE_YYYYMMDD;
    }
}
