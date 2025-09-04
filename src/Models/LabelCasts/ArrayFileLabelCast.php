<?php

namespace Condoedge\Utils\Models\LabelCasts;

class ArrayFileLabelCast extends AbstractLabelCast
{
    public function convert($value, $column)
    {
        if (!$value) return null;
        $value = is_array($value) ? $value : json_decode($value, true);

        return _Rows(collect($value)->map(function ($item, $index) use ($column) {
            $options = $this->options;
            $options['index'] = $index;

            return (new FileLabelCast($this->model, $options))->convert($item, $column);
        })->filter());
    }
}