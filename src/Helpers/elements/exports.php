<?php

function _ExcelExportButton()
{
    return _Link('EXCEL')->icon('download')->outlined()->class('mb-4')->selfPost('pluginMethod', ['method' => 'exportToExcel'])->withAllFormValues()->inModal();
}