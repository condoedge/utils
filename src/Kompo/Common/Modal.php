<?php

namespace Condoedge\Utils\Kompo\Common;

use Condoedge\Utils\Kompo\Plugins\Base\HasComponentPlugins;
use Kompo\Modal as KompoModal;

class Modal extends KompoModal
{
    use HasComponentPlugins;
    
    public $class = 'overflow-y-auto mini-scroll max-w-xl';
    public $style = 'max-height: 95vh; width: 95vw;';

    protected $bodyWrapperClass = 'p-4 md:p-8';
    protected $titleClass = "!text-black !text-xl sm:!text-2xl";
    protected $headerClass = "";

    protected $noHeaderButtons = false;
    protected $hasSubmitButton = true;


    public function render()
    {
        return _Modal(
            _ModalHeader(
                $this->header()
            )->class($this->headerClass),
            _ModalBody(
                $this->body()
            )->class($this->bodyWrapperClass),
        );
    }

    public function header()
    {
        return [
            $this->title(),

            $this->noHeaderButtons ? null : _FlexEnd(
                $this->headerButtons()
            )->class('flex-row-reverse md:flex-row md:ml-8 gap-4')
        ];
    }

    public function title()
    {
        return _ModalTitle($this->_Title, $this->_Icon)?->class($this->titleClass);
    }

    public function headerButtons()
    {
        return $this->hasSubmitButton ? _SubmitButton('general.save') : null;
    }
}
