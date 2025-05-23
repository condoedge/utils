<?php

namespace Condoedge\Utils\Models\ContactInfo\Email;

use Condoedge\Utils\Models\Model;
use Illuminate\Support\Facades\Schema;

class Email extends Model
{
    use \Condoedge\Utils\Models\Traits\BelongsToTeamTrait;

    public const TYPE_EM_PERSONAL = 1;
    public const TYPE_EM_WORK = 2;

    public function save(array $options = [])
    {
        $this->setTeamId();

        parent::save($options);
    }

    /* ENUMS */
    public static function getTypeEmLabels()
    {
        return [
            Email::TYPE_EM_PERSONAL => __('email-personal'),
            Email::TYPE_EM_WORK => __('email-work'),
        ];
    }

    /* RELATIONSHIPS */
    public function emailable()
    {
        return $this->morphTo();
    }

    /* ATTRIBUTES */
    public function getTypeEmLabelAttribute()
    {
        return Email::getTypeEmLabels()[$this->type_em];
    }

    /* CALCULATED FIELDS */
    public function getEmailLabel()
    {
        return $this->address_em;
    }

    public function isSameAddress($address)
    {
        return $this->getEmailLabel() == $address;
    }

    /* SCOPES */
    public function scopeUserOwnedRecords($query)
    {
        return $query->whereIn('emailable_type', config('kompo-utils.morphables-contact-associated-to-user', []))
            ->whereHas('emailable', function ($q) {
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

    /* ACTIONS */
    public function setEmailable($model)
    {
        $this->emailable_type = $model->getRelationType();
        $this->emailable_id = $model->id;
    }

    public function setEmailAddress($address)
    {
        $this->address_em = $address;
    }
    
    public static function createMainFor($emailable, $address)
    {
        if ($emailable->emails()->where('address_em', $address)->exists()) {
            return;
        }

        $email = new Email();
        $email->type_em = Email::TYPE_EM_PERSONAL;
        $email->address_em = $address;
        $email->emailable_id = $emailable->id;
        $email->emailable_type = $emailable->getMorphClass();
        $email->save();

        $emailable->setPrimaryEmail($email->id);
    }
}
