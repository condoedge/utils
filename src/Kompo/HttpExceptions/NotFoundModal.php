<?php

namespace Condoedge\Utils\Kompo\HttpExceptions;

class NotFoundModal extends BlockageModal
{
    public $principalMessage = 'errors.404-title';
    public $secondaryMessage = 'errors.404-subtitle';

    public function actionButton()
    {
        return _Button('errors.back-to-home-page')->redirect('dashboard');
    }
}
