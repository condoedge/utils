<?php

namespace Condoedge\Utils\Models\LabelCasts;

use Condoedge\Utils\Models\Model;

abstract class AbstractLabelCast
{
    protected Model $model;
    protected $options;

    public function __construct(Model $model, array $options = [])
    {
        $this->model = $model;
        $this->options = $options;
    }
    
    abstract public function convert($value, $column);

    public function getLabel($value, $column)
    {
        return $this->convert($value, $column);
    }
}