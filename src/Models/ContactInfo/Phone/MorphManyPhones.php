<?php

namespace Condoedge\Utils\Models\ContactInfo\Phone;

use Illuminate\Database\UniqueConstraintViolationException;

trait MorphManyPhones
{
    /* RELATIONSHIPS */
    public function phones()
    {
        return $this->morphMany(Phone::class, 'phonable');
    }

    public function phone()
    {
        return $this->morphOne(Phone::class, 'phonable')->latest();
    }

    public function primaryPhone()
    {
        return $this->belongsTo(Phone::class, 'primary_phone_id');
    }

    /* SCOPES */

    /* CALCULATED FIELDS */
    public function getPrimaryPhoneNumber(): string
    {
        $ph = $this->primaryPhone;
        if (!$ph) {
            return '';
        }

        return $ph->getFullLabelWithExtension();
    }

    public function getFirstValidPhone()
    {
        return $this->primaryPhone ?: $this->phone;
    }

    public function getFirstValidPhoneLabel()
    {
        return $this->getFirstValidPhone()?->getFullLabelWithExtension();
    }

    public function getFirstValidPhoneRawNumber()
    {
        return $this->getFirstValidPhone()?->getRawFormattedPhoneNumber();
    }

    public function getFirstValidPhoneToInputs()
    {
        $number = $this->getFirstValidPhoneRawNumber();
        $number = trim($number) ? trim('+' . $number) : '';

        return $number;
    }

    /* ATTRIBUTES */
    public function getPrimaryPhoneNumberAttribute(): string
    {
        $ph = $this->primaryPhone;
        if (!$ph) {
            return '';
        }

        return $ph->number_ph . ($ph->extension_ph ? (' - ext:' . $ph->extension_ph) : '');
    }

    /* ACTIONS */
    /**
     * `phones_..._number_ph_unique` counts trashed rows, so the lookup has to see them too,
     * otherwise copying a number the owner once deleted lands as a 1062.
     */
    public function setPhonableAndMakePrimary(?Phone $phone)
    {
        if (!$phone) {
            return;
        }

        $copiedPhone = $this->findPhoneByNumber($phone->number_ph);

        if ($copiedPhone) {
            $this->restorePhoneIfTrashed($copiedPhone);
        } else {
            $copiedPhone = $this->copyPhoneToSelf($phone);
        }

        $this->setPrimaryPhone($copiedPhone->id);
    }

    protected function copyPhoneToSelf(Phone $phone): Phone
    {
        $copiedPhone = $phone->replicate();
        $copiedPhone->setPhonable($this);
        $copiedPhone->deleted_at = null;

        try {
            $copiedPhone->save();
        } catch (UniqueConstraintViolationException $e) {
            // Two submits both read no match. The index settled it, so re-read the winner.
            $copiedPhone = $this->findPhoneByNumber($phone->number_ph);

            if (!$copiedPhone) {
                throw $e;
            }

            $this->restorePhoneIfTrashed($copiedPhone);
        }

        return $copiedPhone;
    }

    public function deletePhones()
    {
        $this->unsetPrimaryPhone();

        $this->phones->each->delete();
    }

    public function deletePhone()
    {
        $this->unsetPrimaryPhone();

        $this->phone?->delete();
    }

    public function setPrimaryPhone($id)
    {
        $this->primary_phone_id = $id;
        $this->save();
    }

    public function unsetPrimaryPhone()
    {
        if ($this->primary_phone_id) {
            $this->primary_phone_id = null;
            $this->save();
        }
    }

    public function createPhoneFromNumberIfNotExists($number)
    {
        $existingPhone = $this->findPhoneByNumber($number);

        if (!$existingPhone) {
            $this->createPhoneFromNumber($number);

            return null;
        }

        $this->restorePhoneIfTrashed($existingPhone);

        return $existingPhone;
    }

    /**
     * The main phone is read as `primaryPhone ?: phone`, so it is written the
     * same way. Comparing against `phone` alone re-created the row the reader
     * was showing from `primaryPhone`, which the unique index rejects.
     */
    public function createOrDeleteMainPhoneFromNumber($number)
    {
        if (!$number) {
            $mainPhone = $this->getFirstValidPhone();

            $this->unsetPrimaryPhone();
            $mainPhone?->delete();

            return;
        }

        $phone = $this->findPhoneByNumber($number);

        if ($phone) {
            $this->restorePhoneIfTrashed($phone);
        } else {
            $phone = $this->createPhoneFromNumber($number);
        }

        if ($this->primary_phone_id != $phone->id) {
            $this->setPrimaryPhone($phone->id);
        }
    }

    public function findPhoneByNumber($number)
    {
        return $this->phones()->withTrashed()->get()
            ->first(fn ($phone) => $phone->hasSameRawNumber($number));
    }

    protected function restorePhoneIfTrashed(Phone $phone): void
    {
        if ($phone->trashed()) {
            $phone->restore();
        }
    }

    public function createPhoneFromNumber($number)
    {
        $existingPhone = new Phone();
        $existingPhone->setPhonable($this);
        $existingPhone->setPhoneNumber($number);
        $existingPhone->save();   
        
        return $existingPhone;
    }

    public function createOrUpdateMainPhone($number)
    {
        if ($duplicate = $this->findPhoneByNumber($number)) {
            $this->restorePhoneIfTrashed($duplicate);

            return;
        }

        $existingPhone = $this->phone;

        if (!$existingPhone) {
            $this->createPhoneFromNumber($number);
        } else {
            $existingPhone->setPhoneNumber($number);
            $existingPhone->save();
        }
    }

    public function manageChangesMainPhone($number)
    {
        $existingPhone = $this->phone;

        if ($number) {
            $this->createOrUpdateMainPhone($number);
        } else {
            $existingPhone?->delete();
        }
    }


    /* ELEMENTS */
    public function getPrimaryPhoneButton()
    {
        $el = _Link()->icon(_Sax('mobile',20))->asPillGrayWhite();

        return $this->primary_phone_number ? $el->href('tel:'.$this->primary_phone_number) : $el->run('() => {alert("No phone found")}');
    }
}
