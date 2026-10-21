<?php

namespace Condoedge\Utils\Models\LabelCasts;

use Illuminate\Support\Facades\Storage;

class YesNoLabelCast extends AbstractLabelCast
{
    public function convert($value, $column)
    {
        if (is_null($value)) return null;

        return $value ? __('utils.yes') : __('utils.no');
    }
}