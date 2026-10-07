<?php

namespace Condoedge\Utils\Models\Traits;

use Condoedge\Utils\Facades\UserModel;
use Condoedge\Utils\Models\Scopes\DeletedByScope;

/**
 * Stamps who soft-deleted a row. Use after SoftDeletes; tables without a `deleted_by` column are left alone.
 */
trait HasDeletedByTrait
{
    protected static $deletedByTables = [];

    public static function bootHasDeletedByTrait()
    {
        // So queries like ->update([deleted_at => now()]) also set the deleted_by column.
        static::addGlobalScope(new DeletedByScope);

        static::softDeleted(fn ($model) => $model->stampDeletedBy());

        // ponytail: instance restore only, a query-level ->restore() keeps the old stamp.
        static::restoring(function ($model) {
            if ($model->hasDeletedByColumn()) {
                $model->deleted_by = null;
            }
        });
    }

    /* RELATIONSHIPS */
    public function deletedBy()
    {
        return $this->belongsTo(config('kompo-auth.user-model', UserModel::getClass()), 'deleted_by');
    }

    /* CALCULATED FIELDS */
    public static function deletedByUserId()
    {
        return auth()->id() ?: config('kompo-auth.default-added-by-modified-by');
    }

    public function hasDeletedByColumn()
    {
        $key = $this->getConnection()->getName() . '.' . $this->getTable();

        // ponytail: per-process memo, a long-lived worker sees a new column only after a restart.
        return static::$deletedByTables[$key] ??= $this->getConnection()->getSchemaBuilder()->hasColumn($this->getTable(), 'deleted_by');
    }

    /* ACTIONS */
    public function stampDeletedBy()
    {
        if (!$this->hasDeletedByColumn()) {
            return;
        }

        $userId = static::deletedByUserId();

        // runSoftDelete() writes a fixed column list, so the stamp needs its own query.
        $this->setKeysForSaveQuery($this->newModelQuery())->toBase()->update(['deleted_by' => $userId]);

        $this->deleted_by = $userId;
        $this->syncOriginalAttribute('deleted_by');
    }
}
