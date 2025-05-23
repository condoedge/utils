<?php

namespace Condoedge\Utils\Models\ContactInfo\Phone;

use Condoedge\Utils\Models\Model;
use Illuminate\Support\Facades\Schema;

class Phone extends Model
{
    use \Condoedge\Utils\Models\Traits\BelongsToTeamTrait;

    public const TYPE_PH_WORK = 1;
    public const TYPE_PH_CELLULAR = 2;
    public const TYPE_PH_HOME = 3;
    public const TYPE_PH_OTHER = 4;
    public const TYPE_PH_FAX = 5;
    
    public function save(array $options = [])
    {
        $this->setTeamId();

        parent::save($options);
    }

    /* ENUMS */
    public static function getTypePhLabels()
    {
        return [
            Phone::TYPE_PH_WORK => __('Work'),
            Phone::TYPE_PH_CELLULAR => __('Cellular'),
            Phone::TYPE_PH_HOME => __('Home'),
            Phone::TYPE_PH_OTHER => __('Other'),
        ];
    }

    /* RELATIONS */
    public function phonable()
    {
        return $this->morphTo();
    }

    /* ATTRIBUTES */
    public function getTypePhLabelAttribute()
    {
        return Phone::getTypePhLabels()[$this->type_ph] ?? '';
    }

    /* SCOPES */
    public function scopeMatchNumber($query, $phoneNumber)
    {
        $query->where('number_ph', $phoneNumber);
    }

    public function scopeUserOwnedRecords($query)
    {
        return $query->whereIn('phonable_type', config('kompo-utils.morphables-contact-associated-to-user', []))
            ->whereHas('phonable', function ($q) {
                $q->where(function($subquery) {
                    $model = $subquery->getModel();

                    if (Schema::hasColumn($model->getTable(), 'user_id')) {
                        $subquery->where('user_id', auth()->id());
                    } elseif (method_exists($model, 'user')) {
                        $subquery->whereHas('user', function($userQuery) {
                            $userQuery->where('id', auth()->id());
                        });
                    }
                });
            });
    }

    /* CALCULATED FIELDS */
    public function getFullLabelWithExtension()
    {
        return $this->getPhoneNumber() . ($this->extension_ph ? (' - ext:' . $this->extension_ph) : '');
    }

    public function getPhoneNumber()
    {
        return $this->number_ph;
    }

    public function isSameNumber($number)
    {
        return $this->getPhoneNumber() == $number; //TODO change after phone sanitizing
    }

    /* ACTIONS */
    public function setPhonable($model)
    {
        $this->phonable_type = $model->getRelationType();
        $this->phonable_id = $model->id;
    }

    public function setPhoneNumber($number)
    {
        //TODO sanitize phone number
        $this->number_ph = $number;
    }

    /* ELEMENTS */
}
