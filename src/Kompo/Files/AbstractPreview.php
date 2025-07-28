<?php

namespace Condoedge\Utils\Kompo\Files;

use Condoedge\Utils\Kompo\Common\Form;
use Illuminate\Database\Eloquent\Relations\Relation;

class AbstractPreview extends Form
{
    public $model;

    protected $fileType;

    public function created()
    {
        $this->fileType = request('type');

        $model = Relation::morphMap()[$this->fileType];

    	$this->model($model::findOrFail(request('id')));
    }
}