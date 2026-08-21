<?php

namespace Condoedge\Utils\Models\ContactInfo\Email;

use Condoedge\Utils\Contracts\Security\HasOwnedRecords;
use Condoedge\Utils\Contracts\Security\ScopedToTeam;
use Condoedge\Utils\Models\Concerns\Security\BelongsToOneTeam;
use Condoedge\Utils\Models\Concerns\Security\OwnedRecordsViaMorphContact;
use Condoedge\Utils\Models\Model;

class Email extends Model implements HasOwnedRecords, ScopedToTeam
{
    use BelongsToOneTeam;
    use \Condoedge\Utils\Models\Traits\BelongsToTeamTrait;
    use OwnedRecordsViaMorphContact;

    protected function morphContactColumnName(): string
    {
        return 'emailable';
    }

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
        $address = trim((string) $address);

        // The unique index compares under utf8mb4_unicode_ci, so the guards have to too.
        return $address !== '' && strcasecmp(trim((string) $this->getEmailLabel()), $address) === 0;
    }

    /* SCOPES */
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
        // A cleared email is only soft-deleted and keeps its slot in the unique index,
        // so a live-rows-only check re-inserted it and hit a 1062.
        if ($existing = $emailable->findEmailByAddress($address)) {
            $emailable->restoreEmailIfTrashed($existing);

            if (!$emailable->primary_email_id) {
                $emailable->setPrimaryEmail($existing->id);
            }

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
