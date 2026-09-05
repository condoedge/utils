<?php

namespace Condoedge\Utils\Kompo\Elements;

use Kompo\Input;
use Condoedge\Utils\Rule\InternationalPhoneRule;

class InternationalPhoneInput extends Input
{
    public $vueComponent = 'InternationalPhoneInput'; // Resolves to VlInternationalPhoneInput

    protected function initialize($label)
    {
        parent::initialize($label);

        $this->config([
            'displayFormat' => 'international', // e164|national|international
            'validateFront' => true,
        ])->noInputWrapper();
    }

    public function defaultCountry(?string $iso2)
    {
        return $this->config(['defaultCountry' => $iso2]);
    }

    public function country(?string $iso2)
    {
        return $this->config(['country' => $iso2]);
    }

    public function displayFormat(string $format)
    {
        return $this->config(['displayFormat' => $format]);
    }

    public function validateFront(bool $enabled = true)
    {
        return $this->config(['validateFront' => $enabled]);
    }

    /**
     * Adds the optional extension ("poste") box. Its value is submitted alongside the number
     * under `<name>_ext`; fields that do not opt in submit nothing and never clear a stored one.
     */
    public function withExtension($default = null)
    {
        return $this->config([
            'withExtension' => true,
            'extensionValue' => $default,
            // Config strings never pass through Kompo's label auto-translation.
            'extensionLabel' => __('crm.phone-extension'),
            'extensionClearLabel' => __('crm.phone-extension-remove'),
        ]);
    }

    public function backendRule()
    {
        return $this->rules([new InternationalPhoneRule]);
    }
}



