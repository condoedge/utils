<?php

namespace Condoedge\Utils\Kompo\Elements;

use Kompo\Input;

/**
 * A one-time code, one box per digit. An invisible input spread over the boxes takes the
 * typing, so paste and one-time-code autofill work as usual. Vue side: VlCodeInput (js-kompo-utils).
 */
class CodeInput extends Input
{
    public $vueComponent = 'CodeInput';

    protected function initialize($label)
    {
        parent::initialize($label);

        $this->noInputWrapper()->length(6);
    }

    public function length(int $length)
    {
        return $this->config(['length' => $length]);
    }
}
