<?php

namespace Condoedge\Utils\Models\LabelCasts;

class ArrayEnumLabelCast extends AbstractLabelCast
{
    public function convert($value, $column)
    {
        if (!$value) return null;
        $value = is_array($value) ? $value : (json_decode($value, true) ?? [$value]);

        return collect($value)->map(function ($item) use ($column) {
            return (new EnumLabelCast($this->model, $this->options))->convert($item, $column);
        })->filter()->implode(', ');
    }
}