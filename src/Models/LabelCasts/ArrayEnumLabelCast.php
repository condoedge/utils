<?php

namespace Condoedge\Utils\Models\LabelCasts;

class ArrayEnumLabelCast extends AbstractLabelCast
{
    public function convert($value, $column)
    {
        if (!$value) return null;
        $value = json_decode($value, true);
        
        return collect($value)->map(function ($item) use ($column) {
            return (new EnumLabelCast($this->model, $this->options))->convert($item, $column);
        })->filter()->implode(', ');
    }
}