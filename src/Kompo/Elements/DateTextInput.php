<?php

namespace Condoedge\Utils\Kompo\Elements;

use Kompo\Input;

class DateTextInput extends Input
{
    public $vueComponent = 'DateTextInput';

    public function initialize($label)
    {
        parent::initialize($label);

        $this->invalidClass('!border !border-red-600');
    }

    public function invalidClass($class) 
    {
        return $this->config(['invalidClass' => $class]);
    }

    public function format($dateFormat): mixed
    {
        return $this->config(['dateFormat' => $dateFormat]);
    }

    public function validateJustFuture($value = 1) 
    {
        return $this->config(['validateJustFuture' => $value]);
    }
}