<?php

namespace Condoedge\Utils\Models\ContactInfo\Maps;

use Condoedge\Utils\Models\Model;
use Illuminate\Support\Facades\Schema;
use Kompo\Place;

class Address extends Model
{
    use \Condoedge\Utils\Models\Traits\BelongsToTeamTrait;

    public const BASE_SEPARATOR = '<br>';

    protected $fillable = [
        'address1',
        'city',
        'state',
        'postal_code',
        'country',
        'street',
        'street_number',
        'lat',
        'lng',
        'external_id',
    ];

    public function save(array $options = [])
    {
        $this->setTeamId();

        parent::save($options);
    }

    /* RELATIONSHIPS */
    public function addressable()
    {
        return $this->morphTo();
    }

    /* SCOPES */
    public function scopeForAddressable($query, $addressableId, $addressableType)
    {
        scopeWhereBelongsTo($query, 'addressable_id', $addressableId);
        scopeWhereBelongsTo($query, 'addressable_type', $addressableType);
    }

    /* ATTRIBUTES */
    public function getAddressLabelAttribute() //Important for displaying loaded value in Place.vue
    {
        return $this->address1.' '.$this->postal_code.' '.$this->city;
    }

    public function setAddressLabelAttribute($value)
    {
        return null;
    }

    /* CALCULATED FIELDS */
    public function getAddressLabel($full = false)
    {
        return collect([
            $this->address1, 
            $full ? $this->getExtraItems() : null,
            $this->city.', '.$this->state,
            $this->postal_code,
        ])->filter()->implode(Address::BASE_SEPARATOR);
    }

    public function getAddressInline($full = false)
    {
        return str_replace(Address::BASE_SEPARATOR, ', ', $this->getAddressLabel($full));
    }

    public function getAddressGoogleLink()
    {
        return 'https://maps.google.com?&daddr='.urlencode(str_replace(Address::BASE_SEPARATOR, ' ', $this->getAddressLabel()));
    }

    public function getAddressHtml($full = false)
    {
        return '<address class="not-italic">'.$this->getAddressLabel($full).'</address>';
    }

    public function getShortAddressLabel()
    {
        return $this->address1.' '.$this->postal_code.' '.$this->city;
    }

    public function getExtraItems()
    {
        return collect([
            $this->address2, 
            $this->address3,
        ])->filter()->implode(Address::BASE_SEPARATOR);
    }


    /* SCOPES */
    public function scopeUserOwnedRecords($query)
    {
        return $query->whereIn('addressable_type', config('kompo-utils.morphables-contact-associated-to-user', []))
            ->whereHas('addressable', function ($q) {
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
    public function setAddressable($model)
    {
        $this->addressable_type = $model->getRelationType();
        $this->addressable_id = $model->id;
    }

    public static function createMainForFromRequest($addressable, $addressData)
    {
        // Calling place we initialize de key => value mapping in places.
        _Place();
        $addressData = is_string($addressData) ? Place::placeToDB($addressData) : $addressData;

        if ($addressable->addresses()->where('address1', $addressData['address1'])->exists()) {
            return;
        }

        $address = new static;
        $address->fill($addressData);
        $address->addressable_id = $addressable->id;
        $address->addressable_type = $addressable->getMorphClass();
        $address->save();

        $addressable->setPrimaryBillingAddress($address->id);
        $addressable->setPrimaryShippingAddress($address->id);
    }

    /* ELEMENTS */
}
