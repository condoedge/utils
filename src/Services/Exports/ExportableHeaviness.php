<?php

namespace Condoedge\Utils\Services\Exports;

enum ExportableHeaviness: int
{
    case LIGHT = 1;
    case MEDIUM = 2;
    case HEAVY = 3;

    public function functionExportName(): string
    {
        return match ($this) {
            self::LIGHT => 'directExportToExcel',
            self::MEDIUM => 'selectExportOptions',
            self::HEAVY => 'exportToExcelViaEmailEl',
        };
    }
}