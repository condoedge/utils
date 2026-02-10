<?php

namespace Condoedge\Utils\Kompo\HttpExceptions;

use Condoedge\Utils\Kompo\Common\Form;

class BlockageModal extends Form
{
    public $class = 'p-8 py-16 overflow-y-auto mini-scroll max-w-xl';

    public $principalMessage = '';
    public $secondaryMessage = '';

    protected $disableCloseButton = false;

    public function render()
    {
        return _Rows(
            _FlexCenter(
                _Img('images/error-403-image.jpg')->class('rounded-full border-black border-2')->style('width: max(20%, 160px);'),
            ),
            _Rows(
                _Html('errors.oops!')->class('text-2xl font-semibold text-center mt-8'),
                _Html($this->principalMessage)->class('text-center text-2xl mt-4'),
                _Html($this->secondaryMessage)->class('text-center mt-8'),
                _FlexCenter(
                    $this->actionButton()
                )->class('mt-3'),
            ),
        );
    }

    public function actionButton()
    {
        if ($this->disableCloseButton) {
            return null;
        }

        return _Button('errors.ok')->closeModal();
    }
}
