<?php

namespace Condoedge\Utils\Services\Exports;

/**
 * Value-object wrapper signaling that a cell value is a currency amount.
 * Used in conjunction with `WithColumnFormatting::columnFormats()` to apply
 * NumberFormat::FORMAT_CURRENCY_USD (or similar) at the Excel cell level.
 * The string form is a plain formatted number with thousands separator so
 * legacy text-based renderers degrade gracefully.
 */
final class ExcelCurrency
{
    public function __construct(
        public readonly float $amount,
        public readonly string $currency = 'USD',
    ) {}

    public function __toString(): string
    {
        return number_format($this->amount, 2, '.', ',');
    }
}
