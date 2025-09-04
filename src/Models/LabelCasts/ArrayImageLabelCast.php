<?php

namespace Condoedge\Utils\Models\LabelCasts;

class ArrayImageLabelCast extends AbstractLabelCast
{
    public function convert($value, $column)
    {
        if (!$value) return null;
        $value = is_array($value) ? $value : json_decode($value, true);
        
        return collect($value)->map(function ($item) use ($column) {
            return (new ImageLabelCast($this->model, $this->options))->convert($item, $column);
        })->filter()->implode(', ');
    }
}