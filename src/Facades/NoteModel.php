<?php

namespace Condoedge\Utils\Facades;

use Kompo\Komponents\Form\KompoModelFacade;

/**
 * @mixin \Condoedge\Utils\Models\Notes\Note
 */
class NoteModel extends KompoModelFacade
{
    protected static function getModelBindKey()
    {
        return 'note-model';
    }
}