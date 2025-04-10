<?php

namespace Condoedge\Utils\Models;

use Illuminate\Database\Eloquent\Model;

class ModelBase extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;
    use \Kompo\Auth\Models\Traits\HasRelationType;
    use \Kompo\Auth\Models\Traits\HasEnhancedLabels;
    use \Kompo\Auth\Models\LabelCasts\HasLabelCasts;

    public const DISPLAY_ATTRIBUTE = null; //OVERRIDE IN CLASS
    public const SEARCHABLE_NAME_ATTRIBUTE = null; //OVERRIDE IN CLASS

    /* CALCULATED FIELDS */
    public static function getNameDisplayKey()
    {
        return static::DISPLAY_ATTRIBUTE ?: static::SEARCHABLE_NAME_ATTRIBUTE;    
    }

    public function getNameDisplay()
    {
        $nameDisplayKey = $this->getNameDisplayKey() ?: 'name';

        return $this->{$nameDisplayKey};
    }
}