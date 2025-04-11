<?php

namespace Condoedge\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Condoedge\Utils\Models\Traits\HasModelPlugins;

class ModelBase extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;
    use \Condoedge\Utils\Models\Traits\HasRelationType;
    use \Condoedge\Utils\Models\Traits\HasEnhancedLabels;
    use \Condoedge\Utils\Models\LabelCasts\HasLabelCasts;
    use HasModelPlugins;

    public const DISPLAY_ATTRIBUTE = null; //OVERRIDE IN CLASS
    public const SEARCHABLE_NAME_ATTRIBUTE = null; //OVERRIDE IN CLASS

    protected static function boot()
    {
        parent::boot();
        static::bootHasPlugins();
    }

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