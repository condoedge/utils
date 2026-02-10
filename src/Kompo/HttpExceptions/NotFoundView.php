<?php

namespace Condoedge\Utils\Kompo\HttpExceptions;

class NotFoundView extends GenericErrorView
{
    protected function errorComponent()
    {
        return new NotFoundModal();
    }
}
