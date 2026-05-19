<?php

namespace Condoedge\Utils\Services\Exports;

use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Value-object wrapper signaling that a cell value is a currency amount.
 * Used in conjunction with `WithColumnFormatting::columnFormats()` to apply
 * NumberFormat::FORMAT_CURRENCY_USD (or similar) at the Excel cell level.
 * The string form is a plain formatted number with thousands separator so
 * legacy text-based renderers degrade gracefully.
 */
final class ExcelCurrency extends AbstractExportType
{
    public function __construct(
        public readonly ?float $amount,
        public readonly string $currency = 'USD',
    ) {}

    public function __toString(): string
    {
        return number_format($this->amount ?? 0, 2, '.', ',');
    }

    public function getFormat(): string
    {
        return NumberFormat::FORMAT_CURRENCY_USD;
    }
}
