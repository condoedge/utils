<?php

namespace Condoedge\Utils\Models\LabelCasts;

use Illuminate\Support\Facades\Storage;

class ImageLabelCast extends AbstractLabelCast
{
    public function convert($value, $column)
    {
        if (!$value) return null;
        
        return '<img src="' . Storage::url($value['path'] ?? '') . '" />';
    }
}