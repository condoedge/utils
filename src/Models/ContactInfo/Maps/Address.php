<?php

namespace Condoedge\Utils\Models\ContactInfo\Maps;

use Condoedge\Utils\Contracts\Security\HasOwnedRecords;
use Condoedge\Utils\Contracts\Security\ScopedToTeam;
use Condoedge\Utils\Models\Concerns\Security\BelongsToOneTeam;
use Condoedge\Utils\Models\Concerns\Security\OwnedRecordsViaMorphContact;
use Condoedge\Utils\Models\Model;
use Kompo\Place;

class Address extends Model implements HasOwnedRecords, ScopedToTeam
{
    use BelongsToOneTeam;
    use \Condoedge\Utils\Models\Traits\BelongsToTeamTrait;
    use OwnedRecordsViaMorphContact;

    protected function morphContactColumnName(): string
    {
        return 'addressable';
    }

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

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];

    /** The columns describing the place itself, carried over whenever a row is copied or reused. */
    public const LOCATION_ATTRIBUTES = [
        'address1',
        'apt_or_suite',
        'postal_code',
        'city',
        'state',
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

    // SETTERS
    public function setLatAttribute($value)
    {
        $this->attributes['lat'] = $value === '' || $value === null ? null : $value;
    }

    public function setLngAttribute($value)
    {
        $this->attributes['lng'] = $value === '' || $value === null ? null : $value;
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

    public function getAddressToGeocode(): array
    {
        return [
            'address' => $this->address1, 
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
        ];
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


    /**
     * What `addresses_addressable_type_addressable_id_lat_lng_unique` is keyed on. Compared at
     * the column's own scale, decimal(16,12), so PHP agrees with the index.
     */
    public function hasSameCoordinatesAs(Address $other): bool
    {
        return $this->lat !== null && $this->lng !== null
            && $this->coordinateKey($this->lat) === $this->coordinateKey($other->lat)
            && $this->coordinateKey($this->lng) === $this->coordinateKey($other->lng);
    }

    /** NULL coordinates are exempt from that index, so the place id still has to dedupe them. */
    public function hasSamePlaceIdAs(Address $other): bool
    {
        return $this->external_id !== null && $this->external_id === $other->external_id;
    }

    protected function coordinateKey($value): ?string
    {
        return $value === null ? null : number_format((float) $value, 12, '.', '');
    }

    /* ACTIONS */
    public function copyLocationFrom(Address $source): void
    {
        foreach (self::LOCATION_ATTRIBUTES as $attribute) {
            $this->{$attribute} = $source->{$attribute};
        }
    }

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
