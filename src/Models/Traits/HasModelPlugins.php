<?php

namespace Condoedge\Utils\Models\Traits;

use Condoedge\Utils\Kompo\Plugins\Base\PluginsManager;

trait HasModelPlugins
{
    protected static $globalPlugins = [];
    // protected $mergePluginsWithParents = true;

    public static function bootHasPlugins()
    {
        static::getPlugins()->each(function ($plugin) {
            $pluginInstance = new $plugin(static::class);
            if (method_exists($pluginInstance, 'onBoot')) {
                $pluginInstance->onBoot();
            }
        });
    }

    public static function getPlugins()
    {
        return collect(array_merge(
            static::$globalPlugins ?? [],
            app(PluginsManager::class)->getPlugins(static::class)
        ));
    }

    public static function setPlugins(array $plugins, $override = false)
    {
        if ($override) {
            static::$globalPlugins = $plugins;
            return;
        }

        static::$globalPlugins = array_merge(static::$globalPlugins ?? [], $plugins);
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
        return collect($this->getPlugins())->map(function ($plugin) {
            $pluginInstance = is_object($plugin) ? $plugin : new $plugin($this);
            if (method_exists($pluginInstance, 'managableMethods')) {
                return $pluginInstance->managableMethods();
            }
        })->flatten()->unique()->all();
    }

    public function __call($method, $args)
    {
        foreach ($this->getPlugins() as $plugin) {
            $pluginInstance = is_object($plugin) ? $plugin : new $plugin($this);
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
