<?php

namespace Condoedge\Utils\Models\Traits;

use Condoedge\Utils\Facades\UserModel;

trait HasAddedModifiedByTrait
{
    public static function userClass()
    {
        return config('kompo-auth.user-model', UserModel::getClass());
    }

    public static function bootHasAddedModifiedByTrait()
    {
        static::saving(function ($model) {
            $model->manageAddedModifiedBy();
        });
    }

    /* RELATIONSHIPS */
    public function addedBy()
    {
        return $this->belongsTo(static::userClass(), 'added_by');
    }

    public function modifiedBy()
    {
        return $this->belongsTo(static::userClass(), 'modified_by');
    }

    /* SCOPES */
    public function scopeForAuthUser($query, $userId = null)
    {
        $query->where('added_by', $userId ?: auth()->id());
    }

    // ACTIONS
    /**
     * Only a logged-in user is stamped. Writes with no auth (jobs, commands, imports) leave the
     * columns as they are: a null added_by means "no logged-in user created this row".
     */
    public function manageAddedModifiedBy()
    {
        if (auth()->check()) {
            if (!$this->getKey() || !$this->exists) {
                $this->added_by = $this->added_by ?: auth()->id();
            }

            $this->modified_by = auth()->id();
        }
    }
}
