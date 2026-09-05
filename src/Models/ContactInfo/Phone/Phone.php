<?php

namespace Condoedge\Utils\Models\ContactInfo\Phone;

use Condoedge\Utils\Contracts\Security\HasOwnedRecords;
use Condoedge\Utils\Contracts\Security\ScopedToTeam;
use Condoedge\Utils\Models\Concerns\Security\BelongsToOneTeam;
use Condoedge\Utils\Models\Concerns\Security\OwnedRecordsViaMorphContact;
use Condoedge\Utils\Models\Model;
use Propaganistas\LaravelPhone\PhoneNumber;

class Phone extends Model implements HasOwnedRecords, ScopedToTeam
{
    use BelongsToOneTeam;
    use \Condoedge\Utils\Models\Traits\BelongsToTeamTrait;
    use OwnedRecordsViaMorphContact;

    protected function morphContactColumnName(): string
    {
        return 'phonable';
    }

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

    /* CALCULATED FIELDS */
    public function getFullLabelWithExtension()
    {
        return $this->getFormattedPhoneNumber() . ($this->extension_ph ? (' - ext:' . $this->extension_ph) : '');
    }

    public function getFormattedPhoneNumber()
    {
        try {
            $defaultCountry = config('kompo-utils.default-country-phone', 'CA');

            try {
                 $phone = new PhoneNumber($this->getPhoneNumber()); // if the country code is included
                 $phone->formatNational(); // Format to check if it's valid
            } catch (\Propaganistas\LaravelPhone\Exceptions\NumberParseException $e) {
                $phone = new PhoneNumber($this->getPhoneNumber(), $defaultCountry); // force default country
            }

            return $phone->formatInternational(); // +1 (514) 702-8066
        } catch (\Exception $e) {
           return $this->getPhoneNumber();
        }
    }

    /** Digits of the number alone. The extension is not part of it and must not be welded on. */
    public function getRawFormattedPhoneNumber()
    {
        return preg_replace('/\D+/', '', $this->getFormattedPhoneNumber());
    }

    public function getPhoneNumber()
    {
        return $this->number_ph;
    }

    public function isSameNumber($number)
    {
        return $this->getRawFormattedPhoneNumber() == preg_replace('/\D+/', '', $number);
    }

    /**
     * Compares the stored number alone, extension excluded — this is what the
     * (morph, number_ph) unique index collides on.
     */
    public function hasSameRawNumber($number): bool
    {
        $candidate = static::canonicalNumberDigits($number);

        return $candidate !== '' && static::canonicalNumberDigits($this->number_ph) === $candidate;
    }

    /**
     * E.164 digits. Stored rows predate the country code the form now submits, so both
     * sides are parsed before comparison — otherwise a number never matches itself.
     */
    protected static function canonicalNumberDigits($number): string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);

        if ($digits === '') {
            return '';
        }

        try {
            $phone = new PhoneNumber((string) $number, config('kompo-utils.default-country-phone', 'CA'));

            return preg_replace('/\D+/', '', $phone->formatE164());
        } catch (\Exception $e) {
            return $digits;
        }
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

    public function setExtension($extension)
    {
        $this->extension_ph = $extension;
    }

    /* ELEMENTS */
}
