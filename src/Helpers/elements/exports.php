<?php

function _ExcelExportButton($extraParams = [])
{
    return _Link('EXCEL')->icon('download')->outlined()->class('mb-4')->selfPost('pluginMethod', ['method' => 'exportToExcel', ...$extraParams])->withAllFormValues()->inModal();
}