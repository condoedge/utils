<?php

namespace Condoedge\Utils\Models\Plugins;

class ModelPlugin 
{
    protected $modelClass;

    public function __construct($modelClass)
    {
        $this->modelClass = $modelClass;
    }
}