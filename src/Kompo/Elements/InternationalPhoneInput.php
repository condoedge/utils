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
        ]);
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

    public function backendRule()
    {
        return $this->rules([new InternationalPhoneRule]);
    }
}



