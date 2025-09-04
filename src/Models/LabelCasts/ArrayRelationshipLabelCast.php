<?php

namespace Condoedge\Utils\Models\LabelCasts;

class ArrayRelationshipLabelCast extends AbstractLabelCast
{
    public function convert($value, $column)
    {
        if (!$value) return null;
        $value = is_array($value) ? $value : json_decode($value, true);

        return collect($value)->map(function ($item,  $index) use ($column) {
            $options = $this->options;
            $options['index'] = $index;

            return (new RelationshipLabelCast($this->model, $options))->convert($item, $column);
        })->filter()->implode(', ');
    }
}