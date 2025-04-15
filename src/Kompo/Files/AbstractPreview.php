<?php

namespace Condoedge\Utils\Kompo\Files;

use Condoedge\Utils\Kompo\Common\Form;
use Illuminate\Database\Eloquent\Relations\Relation;

class AbstractPreview extends Form
{
    public $model;

    public function created()
    {
        $model = Relation::morphMap()[request('type')];

    	$this->model($model::findOrFail(request('id')));
    }
}