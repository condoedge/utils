<?php

function _ExcelExportButton($extraParams = [])
{
    return _Link('Excel')->icon('download')->outlined()->class('mb-4')->selfPost('pluginMethod', ['method' => 'exportToExcel', ...$extraParams])->withAllFormValues()->inModal();
}