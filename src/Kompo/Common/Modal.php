<?php

namespace Condoedge\Utils\Kompo\Common;

use Condoedge\Utils\Kompo\Plugins\Base\HasComponentPlugins;
use Kompo\Modal as KompoModal;

class Modal extends KompoModal
{
    use HasComponentPlugins;
    
    public $class = 'overflow-y-auto mini-scroll max-w-xl';
    public $style = 'max-height: 95vh; width: 95vw;';

    protected $hasSubmitButton = true;

    public function render()
    {
        return _Modal(
            _ModalHeader(
                $this->header()
            ),
            _ModalBody(
                $this->body()
            )->class('!p-2 md:!p-8 !pt-2'),
        );
    }

    public function header()
    {
        return [
            _ModalTitle($this->_Title, $this->_Icon)?->class('!text-black !text-xl sm:!text-2xl'),

            $this->noHeaderButtons ? null : _FlexEnd(
                $this->headerButtons()
            )->class('flex-row-reverse md:flex-row md:ml-8 gap-4')
        ];
    }

    public function headerButtons()
    {
        return $this->hasSubmitButton ? _SubmitButton('general.save') : null;
    }
}
