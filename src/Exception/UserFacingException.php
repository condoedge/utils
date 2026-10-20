<?php

namespace Condoedge\Utils\Exception;

/**
 * A refusal the user can understand and act on. Presenters may catch it for context;
 * otherwise UserFacingExceptionRenderer turns it into an error alert on Kompo actions.
 */
interface UserFacingException extends \Throwable
{
    public function userMessage(): string;
}
