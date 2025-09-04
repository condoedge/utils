<?php

namespace Condoedge\Utils\Models\LabelCasts;

use Illuminate\Support\Facades\Storage;

class YesNoLabelCast extends AbstractLabelCast
{
    public function convert($value, $column)
    {
        if (!$value) return null;

        return $value ? __('translate.yes') : __('translate.no');
    }
}