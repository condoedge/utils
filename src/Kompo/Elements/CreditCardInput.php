<?php

namespace Condoedge\Utils\Kompo\Elements;

use Kompo\Input;

class CreditCardInput extends Input
{
    public $vueComponent = 'CreditCardInput';

    public function initialize($label)
    {
        parent::initialize($label);

        $this->invalidClass('!border !border-red-600');
    }

    public function invalidClass($class)
    {
        return $this->config(['invalidClass' => $class]);
    }
}