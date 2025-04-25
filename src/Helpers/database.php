<?php

use Illuminate\Database\Eloquent\Relations\Relation;

function processDelimiters($sql)
{
    $sql = preg_replace('/DELIMITER\s*(\S+)/', '', $sql);
    
    $sql = str_replace('$$', ';', $sql);
    
    return $sql;
}

function wildcardSpace($search)
{
    return '%' . str_replace(' ', '%', $search) . '%';
}

if (!function_exists('addMetaData')) {
    function addMetaData($table)
    {
        $table->id();
        addedModifiedByColumns($table);

        // $table->timestamps();

        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->useCurrentOnUpdate();

        $table->softDeletes();
    }
}

if (!function_exists('addedModifiedByColumns')) {
    function addedModifiedByColumns($table)
    {
        $table->foreignId('added_by')->nullable()->constrained('users');
        $table->foreignId('modified_by')->nullable()->constrained('users');
    }
}


if (!function_exists('isWhereCondition')) {
    function isWhereCondition($argument)
    {
        return is_null($argument) || is_string($argument) || is_int($argument);
    }
}

if (!function_exists('scopeWhereBelongsTo')) {
    function scopeWhereBelongsTo($query, $columnName, $itemOrItems, $defaultValue = null)
    {
        if (isWhereCondition($itemOrItems)) {
            $query->where($columnName, $itemOrItems ?: $defaultValue);
        } else {
            $query->whereIn($columnName, $itemOrItems);
        } 
    }
}

function getRawSqlToCopy($model)
{
    $builder = \DB::table($model->getTable());

    $query = \DB::pretend(function() use ($builder, $model)
    {
        return $builder->insert($model->getAttributes());
    });
    
    $bindings = [];
    collect($query[0]['bindings'])->each(function($binding) use (&$bindings)
    {
        $binding = str_replace("'", "\\'", $binding);
        $bindings[] = "'$binding'";
    });

    $insertStatement = \Str::replaceArray('?', $bindings, $query[0]['query']);

    return $insertStatement;
}

function findOrFailMorphModel($modelId, $modelType)
{
	$model = Relation::morphMap()[$modelType];

    return $model::findOrFail($modelId); 
}