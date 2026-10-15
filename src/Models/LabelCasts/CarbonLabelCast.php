<?php

namespace Condoedge\Utils\Models\LabelCasts;

use Illuminate\Support\Facades\Storage;

class CarbonLabelCast extends AbstractLabelCast
{
    public function convert($value, $column)
    {
        if (!$value) return null;

        $format = $this->options['format'] ?? 'M d, Y H:i';

        // Change-log old_data holds the raw string, not the casted Carbon.
        if (!$value instanceof \DateTimeInterface) {
            $value = \Carbon\Carbon::parse($value);
        }

        return $value->format($format);
    }
}