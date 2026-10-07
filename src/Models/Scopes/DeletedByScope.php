<?php

namespace Condoedge\Utils\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class DeletedByScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        //
    }

    // Replaces SoftDeletingScope's delete: query-level deletes fire no model events.
    public function extend(Builder $builder)
    {
        $builder->onDelete(function (Builder $builder) {
            $model = $builder->getModel();
            $hasJoins = count((array) $builder->getQuery()->joins) > 0;
            $column = fn ($name) => $hasJoins ? $model->qualifyColumn($name) : $name;

            $columns = [$column($model->getDeletedAtColumn()) => $model->freshTimestampString()];

            if ($model->hasDeletedByColumn()) {
                $columns[$column('deleted_by')] = $model::deletedByUserId();
            }

            return $builder->update($columns);
        });
    }
}
