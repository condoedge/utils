<?php

namespace Condoedge\Utils\Services\Exports;

use Kompo\TableRow;

class TableExportableToExcel extends ComponentToExportableToExcel
{
    public $layout = 'Table';
    public $card = TableRow::class;
}