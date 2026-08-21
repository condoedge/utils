<?php

namespace Condoedge\Utils\Models\ContactInfo\Email;

trait MorphManyEmails
{
    /* RELATIONSHIPS */
    public function emails()
    {
        return $this->morphMany(Email::class, 'emailable');
    }

    public function email()
    {
        return $this->morphOne(Email::class, 'emailable')->latest();
    }

    public function primaryEmail()
    {
        return $this->belongsTo(Email::class, 'primary_email_id');
    }

    /* CALCULATED FIELDS */
    public function getPrimaryEmailAddress(): string
    {
        return $this->primaryEmail?->getEmailLabel() ?: '';
    }

    public function getFirstValidEmail()
    {
        return $this->primaryEmail ?: $this->email;
    }

    public function getFirstValidEmailLabel()
    {
        return $this->getFirstValidEmail()?->getEmailLabel();
    }

    /* ATTRIBUTES */
    public function getPrimaryEmailAddressAttribute(): string
    {
        return $this->primaryEmail?->getEmailLabel() ?: '';
    }

    /* ACTIONS */
    public function deleteEmails()
    {
        $this->unsetPrimaryEmail();

        $this->emails->each->delete();
    }

    public function deleteEmail()
    {
        $this->unsetPrimaryEmail();

        $this->email?->delete();
    }

    public function setPrimaryEmail($id)
    {
        $this->primary_email_id = $id;
        $this->save();
    }

    public function unsetPrimaryEmail()
    {
        if ($this->primary_email_id) {
            $this->primary_email_id = null;
            $this->save();
        }
    }

    public function createOrDeleteMainEmailFromAddress($address)
    {
        $existingEmail = $this->email;

        if (!$address){
            $existingEmail?->delete();
        } elseif ($existing = $this->findEmailByAddress($address)) {
            $this->restoreEmailIfTrashed($existing);
        } else {
            $this->createEmailFromAddress($address);
        }
    }

    public function createEmailFromAddress($address)
    {
        $existingEmail = new Email();
        $existingEmail->setEmailable($this);
        $existingEmail->setEmailAddress($address);
        $existingEmail->save();        
    }

    public function findEmailByAddress($address)
    {
        return $this->emails()->withTrashed()->get()
            ->first(fn ($email) => $email->isSameAddress($address));
    }

    public function restoreEmailIfTrashed(Email $email): void
    {
        if ($email->trashed()) {
            $email->restore();
        }
    }

    public function createOrUpdateMainEmail($address)
    {
        if ($existing = $this->findEmailByAddress($address)) {
            $this->restoreEmailIfTrashed($existing);

            return;
        }

        $existingEmail = $this->email;

        if (!$existingEmail) {
            Email::createMainFor($this, $address);
        } else {
            $existingEmail->setEmailAddress($address);
            $existingEmail->save();
        }
    }

    public function manageChangesMainEmail($address)
    {
        $existingEmail = $this->email;

        if ($address) {
            $this->createOrUpdateMainEmail($address);
        } else {
            $existingEmail?->delete();
        }
    }


    /* ELEMENTS */
    public function getPrimaryEmailButton()
    {
        $el = _Link()->icon(_Sax('sms',20))->asPillGrayWhite();

        return $this->primary_email_address ? $el->href('mailto:'.$this->primary_email_address) : $el->run('() => {alert("No email found")}');
    }
}
