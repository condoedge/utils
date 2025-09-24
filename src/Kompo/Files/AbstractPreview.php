<?php

namespace Condoedge\Utils\Kompo\Files;

use Condoedge\Utils\Kompo\Common\Form;
use Illuminate\Database\Eloquent\Relations\Relation;

class AbstractPreview extends Form
{
    public $model;

    protected $fileType;
    protected $fileId;

    public function created()
    {
        $this->fileType = $this->prop('type') ?? request('type');
        $this->fileId = $this->prop('id') ?? request('id');

        $model = Relation::morphMap()[$this->fileType];

    	$this->model($model::findOrFail($this->fileId));
    }
}