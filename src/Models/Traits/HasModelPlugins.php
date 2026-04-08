<?php

namespace Condoedge\Utils\Models\Traits;

use Condoedge\Utils\Kompo\Plugins\Base\PluginsManager;

trait HasModelPlugins
{
    use InterceptsRelations;

    protected static $globalPlugins = [];
    // protected $mergePluginsWithParents = true;

    /**
     * Cached plugin instances per model instance.
     * Avoids creating new plugin objects on every getAttribute/getAttributes call.
     */
    protected $resolvedPluginInstances = null;

    /**
     * Per-instance security state. Stored on the model to avoid static array lookups
     * and getModelKey() calls. Garbage collected with the model instance.
     */
    protected $_securityState = null;

    /**
     * Get the security state for this model instance.
     * Lazily created on first access.
     */
    public function getSecurityState(): \Kompo\Auth\Models\Plugins\Services\ModelSecurityState
    {
        if ($this->_securityState === null) {
            $this->_securityState = new \Kompo\Auth\Models\Plugins\Services\ModelSecurityState();
        }
        return $this->_securityState;
    }

    /**
     * Reentrance guard per method.
     * Prevents deep recursion when a plugin's processing accesses other
     * attributes/relations on the same model instance.
     */
    protected $pluginProcessing = [];

    /**
     * Cached resolved plugin list per class.
     */
    protected static $resolvedPlugins = [];

    public static function bootHasModelPlugins()
    {
        static::getPlugins()->each(function ($plugin) {
            $pluginInstance = new $plugin(static::class);
            if (method_exists($pluginInstance, 'onBoot')) {
                $pluginInstance->onBoot();
            }
        });
    }

    /**
     * Get or create cached plugin instances for this model instance.
     */
    protected function getPluginInstances(): array
    {
        if ($this->resolvedPluginInstances === null) {
            $this->resolvedPluginInstances = static::getPlugins()->map(function ($plugin) {
                return is_object($plugin) ? $plugin : new $plugin($this);
            })->all();
        }

        return $this->resolvedPluginInstances;
    }

    public function newCollection(array $models = [])
    {
        foreach ($this->getPluginInstances() as $pluginInstance) {
            if (method_exists($pluginInstance, 'newCollection')) {
                $value = $pluginInstance->newCollection($this, $models);

                if ($value !== false) {
                    return $value;
                }
            }
        }

        return parent::newCollection($models);
    }

    public function getAttributes()
    {
        // Plugin processing is intentionally skipped here. Reasons:
        // 1. getAttributes() is called by Eloquent internally on EVERY attribute access
        //    (getAttributeValue → getAttributeFromArray → getAttributes), on every syncOriginal
        //    during model construction, and on every relationship hydration — thousands of times.
        // 2. Field protection is already handled at two other layers:
        //    - Batch mode: SecuredModelCollection → setRawAttributes (strips columns at source)
        //    - Per-attribute: getAttribute() plugin runs isBlockedRelationship per access
        // 3. HasSecurity::getAttributes() is a no-op — running plugins here is pure overhead.
        return parent::getAttributes();
    }

    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if (!empty($this->pluginProcessing['getAttribute'])) {
            return $value;
        }

        $this->pluginProcessing['getAttribute'] = true;

        try {
            foreach ($this->getPluginInstances() as $pluginInstance) {
                if (method_exists($pluginInstance, 'getAttribute')) {
                    $value = $pluginInstance->getAttribute($this, $key, $value);
                }
            }
        } finally {
            unset($this->pluginProcessing['getAttribute']);
        }

        return $value;
    }

    protected function getRelationshipFromMethod($method)
    {
        if (!empty($this->pluginProcessing['getRelationshipFromMethod'])) {
            return parent::getRelationshipFromMethod($method);
        }

        $this->pluginProcessing['getRelationshipFromMethod'] = true;

        try {
            foreach ($this->getPluginInstances() as $pluginInstance) {
                if (method_exists($pluginInstance, 'getRelationshipFromMethod')) {
                    $value = $pluginInstance->getRelationshipFromMethod($this, $method);

                    if ($value !== false) {
                        return $value;
                    }
                }
            }

            return parent::getRelationshipFromMethod($method);
        } finally {
            unset($this->pluginProcessing['getRelationshipFromMethod']);
        }
    }

    public static function getPlugins()
    {
        $class = static::class;

        if (!isset(static::$resolvedPlugins[$class])) {
            static::$resolvedPlugins[$class] = collect(array_merge(
                static::$globalPlugins ?? [],
                app(PluginsManager::class)->getPlugins($class)
            ))->unique();
        }

        return static::$resolvedPlugins[$class];
    }

    public static function setPlugins(array $plugins, $override = false)
    {
        if ($override) {
            static::$globalPlugins = $plugins;
        } else {
            static::$globalPlugins = array_merge(static::$globalPlugins ?? [], $plugins);
        }

        // Invalidate caches since plugins changed
        unset(static::$resolvedPlugins[static::class]);
    }

    public static function excludePlugins(array $plugins)
    {
        if (is_string($plugins)) {
            $plugins = [$plugins];
        }

        static::$globalPlugins = array_filter(static::$globalPlugins ?? [], function ($plugin) use ($plugins) {
            return !in_array($plugin, $plugins);
        });

        // Invalidate caches since plugins changed
        unset(static::$resolvedPlugins[static::class]);
    }

    public static function getGlobalPlugins()
    {
        return property_exists(static::class, 'globalPlugins') ?
            static::$globalPlugins : null;
    }

    public static function hasMergePluginsWithParents()
    {
        return property_exists(static::class, 'mergePluginsWithParents') ?
            static::$mergePluginsWithParents : true;
    }

    protected function getManagableMethods()
    {
        return collect($this->getPluginInstances())->map(function ($pluginInstance) {
            if (method_exists($pluginInstance, 'managableMethods')) {
                return $pluginInstance->managableMethods();
            }
        })->flatten()->unique()->all();
    }

    public function __call($method, $args)
    {
        foreach ($this->getPluginInstances() as $pluginInstance) {
            if (method_exists($pluginInstance, 'managableMethods')) {
                $methods = $pluginInstance->managableMethods();
                if (in_array($method, $methods)) {
                    return $pluginInstance->$method($this, ...$args);
                }
            }
        }

        return is_callable(['parent', '__call']) ? parent::__call($method, $args) : null;
    }
}
