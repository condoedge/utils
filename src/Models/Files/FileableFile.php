<?php

namespace Condoedge\Utils\Models\Files;

use Condoedge\Utils\Facades\FileModel;
use Condoedge\Utils\Models\ModelBase;

class FileableFile extends ModelBase
{
    protected $table = 'fileable_file';

    /* RELATIONS */
    public function fileable()
    {
        return $this->morphTo();
    }

    public function file()
    {
        return $this->belongsTo(FileModel::getClass());
    }

    /* SCOPES */
    

    /* ATTRIBUTES */


    /* ACTIONS */

    /* ELEMENTS */
    
}
