<?php

namespace Condoedge\Utils\Services\Exports;

abstract class AbstractExportType
{
    abstract public function getFormat(): string;
}