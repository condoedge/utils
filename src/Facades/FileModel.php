<?php

namespace Condoedge\Utils\Facades;

use Kompo\Komponents\Form\KompoModelFacade;

/**
 * @mixin \Condoedge\Utils\Models\Files\File
 */
class FileModel extends KompoModelFacade
{
    protected static function getModelBindKey()
    {
        return FILE_MODEL_KEY;
    }
}