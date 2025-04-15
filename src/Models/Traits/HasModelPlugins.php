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


}
