<?php

namespace Condoedge\Utils\Exception;

/**
 * Thrown with an already translated message the user is meant to read.
 */
class ActionRefusedException extends \RuntimeException implements UserFacingException
{
    public function userMessage(): string
    {
        return $this->getMessage();
    }
}
