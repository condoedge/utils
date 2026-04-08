<?php

namespace Condoedge\Utils\Models\Traits;

/**
 * Overrides Eloquent relationship methods to allow plugins to intercept relation creation.
 * Used by HasModelPlugins — keeps relationship overrides separate from core plugin logic.
 */
trait InterceptsRelations
{
    /**
     * Cache: does this model class have any plugin that intercepts relation creation?
     * Requires both: global config enabled AND a plugin that opts in.
     */
    protected static $hasRelationInterceptor = [];

    protected function hasRelationInterceptor(): bool
    {
        if (!config('kompo-utils.intercept-relations', false)) {
            return false;
        }

        $class = static::class;

        if (!isset(static::$hasRelationInterceptor[$class])) {
            static::$hasRelationInterceptor[$class] = false;

            foreach ($this->getPluginInstances() as $pluginInstance) {
                if (method_exists($pluginInstance, 'interceptRelation')) {
                    static::$hasRelationInterceptor[$class] = true;
                    break;
                }
            }
        }

        return static::$hasRelationInterceptor[$class];
    }

    /**
     * Delegate to plugins to intercept a relationship query.
     * Uses cached plugin instances from getPluginInstances() to avoid creating
     * new plugin objects on every relationship creation.
     */
    protected function pluginInterceptRelation($relation, string $relationName)
    {
        foreach ($this->getPluginInstances() as $pluginInstance) {
            if (method_exists($pluginInstance, 'interceptRelation')) {
                $result = $pluginInstance->interceptRelation($this, $relation, $relationName);
                if ($result !== false) {
                    return $result;
                }
            }
        }

        return $relation;
    }

    protected function interceptRelationIfNeeded($relation)
    {
        if (!$this->hasRelationInterceptor()) {
            return $relation;
        }

        // Frame 0: interceptRelationIfNeeded, Frame 1: morphOne/hasMany/etc override, Frame 2: actual relationship method
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2]['function'] ?? null;
        return $caller ? $this->pluginInterceptRelation($relation, $caller) : $relation;
    }

    public function hasOne($related, $foreignKey = null, $localKey = null)
    {
        return $this->interceptRelationIfNeeded(parent::hasOne($related, $foreignKey, $localKey));
    }

    public function hasMany($related, $foreignKey = null, $localKey = null)
    {
        return $this->interceptRelationIfNeeded(parent::hasMany($related, $foreignKey, $localKey));
    }

    public function belongsTo($related, $foreignKey = null, $ownerKey = null, $relation = null)
    {
        // Fix Laravel's backtrace guess — our override shifts the frame
        if (is_null($relation)) {
            $relation = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
        }

        return $this->interceptRelationIfNeeded(parent::belongsTo($related, $foreignKey, $ownerKey, $relation));
    }

    public function belongsToMany($related, $table = null, $foreignPivotKey = null, $relatedPivotKey = null, $parentKey = null, $relatedKey = null, $relation = null)
    {
        return $this->interceptRelationIfNeeded(parent::belongsToMany($related, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relation));
    }

    public function morphOne($related, $name, $type = null, $id = null, $localKey = null)
    {
        return $this->interceptRelationIfNeeded(parent::morphOne($related, $name, $type, $id, $localKey));
    }

    public function morphMany($related, $name, $type = null, $id = null, $localKey = null)
    {
        return $this->interceptRelationIfNeeded(parent::morphMany($related, $name, $type, $id, $localKey));
    }

    public function morphTo($name = null, $type = null, $id = null, $ownerKey = null)
    {
        // Fix Laravel's backtrace guess — our override shifts the frame
        if (is_null($name)) {
            $name = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
        }

        return $this->interceptRelationIfNeeded(parent::morphTo($name, $type, $id, $ownerKey));
    }

    public function morphToMany($related, $name, $table = null, $foreignPivotKey = null, $relatedPivotKey = null, $parentKey = null, $relatedKey = null, $relation = null, $inverse = false)
    {
        return $this->interceptRelationIfNeeded(parent::morphToMany($related, $name, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relation, $inverse));
    }

    public function morphedByMany($related, $name, $table = null, $foreignPivotKey = null, $relatedPivotKey = null, $parentKey = null, $relatedKey = null, $relation = null)
    {
        return $this->interceptRelationIfNeeded(parent::morphedByMany($related, $name, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relation));
    }

    public function hasOneThrough($related, $through, $firstKey = null, $secondKey = null, $localKey = null, $secondLocalKey = null)
    {
        return $this->interceptRelationIfNeeded(parent::hasOneThrough($related, $through, $firstKey, $secondKey, $localKey, $secondLocalKey));
    }

    public function hasManyThrough($related, $through, $firstKey = null, $secondKey = null, $localKey = null, $secondLocalKey = null)
    {
        return $this->interceptRelationIfNeeded(parent::hasManyThrough($related, $through, $firstKey, $secondKey, $localKey, $secondLocalKey));
    }
}
