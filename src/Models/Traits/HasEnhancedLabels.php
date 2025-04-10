<?php

namespace Condoedge\Utils\Models\Traits;

trait HasEnhancedLabels 
{
    protected $columnsLabels = [];
    
    public function getColumnLabel($column)
	{
		return __($this->columnsLabels[$column] ?? strtolower(class_basename($this) . '.' . $column));
	}    
}