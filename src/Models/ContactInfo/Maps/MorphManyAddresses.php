<?php

namespace Condoedge\Utils\Models\ContactInfo\Maps;

use Illuminate\Database\UniqueConstraintViolationException;


trait MorphManyAddresses
{
    /* RELATIONSHIPS */
    public function addresses()
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function address()
    {
        return $this->morphOne(Address::class, 'addressable')->latest();
    }

    // Queryable version of the `address` relation: its latest() ordering is dropped by whereHas, one-of-many isn't.
    // The closure keeps the aggregate subquery on team addresses only, otherwise it groups the whole addresses table.
    public function latestAddress()
    {
        return $this->morphOne(Address::class, 'addressable')->ofMany(
            ['created_at' => 'MAX', 'id' => 'MAX'],
            fn ($query) => $query->where('addresses.addressable_type', $this->getMorphClass()),
        );
    }


    public function primaryBillingAddress()
    {
        return $this->belongsTo(Address::class, 'primary_billing_address_id');
    }

    public function primaryShippingAddress()
    {
        return $this->belongsTo(Address::class, 'primary_shipping_address_id');
    }

    /* CALCULATED FIELDS */
    public function getPrimaryAddressLabel(): string
    {
        $pa = $this->address;
        if (!$pa) {
            return '';
        }

        return $pa->getAddressLabel();
    }

    public function getFirstValidAddress()
    {
        return $this->address ?: $this->address()->first();
    }

    public function getFirstValidAddressLabel()
    {
        return $this->getFirstValidAddress()?->getAddressLabel();
    }

    /* ACTIONS */
    public function setAddressableAndMakeBilling(?Address $address)
    {
        if (!$address) {
            return;
        }

        $this->setPrimaryBillingAddress($this->copyAddressToSelf($address)->id);
    }

    /**
     * One row per location per owner. `addresses_..._lat_lng_unique` is keyed on the
     * coordinates and counts trashed rows, so looking the copy up by `external_id` alone
     * kept re-inserting a row the index already held.
     *
     * The coordinates come first on purpose: matching what the index matches means the row
     * we hand back can be resynced without ever landing on another row's key.
     */
    public function findSameAddress(Address $address): ?Address
    {
        $ownedAddresses = $this->addresses()->withTrashed()->get();

        return $ownedAddresses->first(fn ($existing) => $existing->hasSameCoordinatesAs($address))
            ?: $ownedAddresses->first(fn ($existing) => $existing->hasSamePlaceIdAs($address));
    }

    protected function copyAddressToSelf(Address $address): Address
    {
        $copiedAddress = $this->findSameAddress($address) ?: $address->replicate();

        $copiedAddress->setAddressable($this);
        $copiedAddress->copyLocationFrom($address);

        try {
            $this->saveAddressCopy($copiedAddress);
        } catch (UniqueConstraintViolationException $e) {
            // Two submits both read no match. The index settled it, so re-read the winner.
            $copiedAddress = $this->findSameAddress($address);

            if (!$copiedAddress) {
                throw $e;
            }

            $this->saveAddressCopy($copiedAddress);
        }

        return $copiedAddress;
    }

    protected function saveAddressCopy(Address $address): void
    {
        if ($address->exists && $address->trashed()) {
            $address->restore();

            return;
        }

        $address->deleted_at = null;
        $address->save();
    }

    public function deleteAddresses()
    {
        $this->unsetPrimaryAddresses();

        $this->addresses->each->delete();
    }

    public function deleteAddress()
    {
        $this->unsetPrimaryAddresses();

        $this->address?->delete();
    }

    public function setPrimaryBillingAddress($id)
    {
        $this->primary_billing_address_id = $id;
        $this->save();
    }

    public function setPrimaryShippingAddress($id)
    {
        $this->primary_shipping_address_id = $id;
        $this->save();
    }

    public function unsetPrimaryAddresses()
    {
        if ($this->primary_billing_address_id) {
            $this->primary_billing_address_id = null;
            $this->save();
        }

        if ($this->primary_shipping_address_id) {
            $this->primary_shipping_address_id = null;
            $this->save();
        }
    }
}
