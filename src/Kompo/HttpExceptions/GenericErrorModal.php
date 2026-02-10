<?php

namespace Condoedge\Utils\Kompo\HttpExceptions;

class GenericErrorModal extends BlockageModal
{
    public function created()
    {
        $this->principalMessage = $this->prop('principal_message');
        $this->secondaryMessage = $this->prop('secondary_message');
    }

    public function actionButton()
    {
        return _Rows(
            _Html('errors.please-contact-us-to-report-this-error')->class('text-gray-500 text-sm text-center'),
        );
    }
}
