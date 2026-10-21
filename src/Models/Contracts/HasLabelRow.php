<?php

namespace Condoedge\Utils\Models\Contracts;

interface HasLabelRow
{
    /**
     * One display row as [header label key => value], or null for a row with nothing filled in.
     */
    public function labelRow(): ?array;
}
