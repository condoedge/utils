<?php

namespace Condoedge\Utils\Models\LabelCasts;

use Illuminate\Support\Facades\Storage;

class CarbonLabelCast extends AbstractLabelCast
{
    public function convert($value, $column)
    {
        if (!$value) return null;

        $format = $this->options['format'] ?? 'M d, Y H:i';

        return $value->format($format);
    }
}