<?php

namespace Condoedge\Utils\Kompo\Plugins;

trait HasPlugins
{
    protected static $plugins = null;
    protected $mergePluginsWithParents = true;

    public function booted()
    {
        $this->getPlugins()->each(function ($plugin) {
            $pluginInstance = new $plugin($this);
            if (method_exists($pluginInstance, 'onBoot')) {
                $pluginInstance->onBoot();
            }
        });
    }

    public function __call($method, $args)
    {
        $this->getPlugins()->each(function ($plugin)  use ($method, $args) {
            $pluginInstance = new $plugin($this);
            if (method_exists($pluginInstance, 'managableMethods')) {
                $methods = $pluginInstance->managableMethods();
                if (in_array($method, $methods)) {
                    return $pluginInstance->$method(...$args);
                }
            }
        });
    }
    
    public function authorize()
    {
        $this->getPlugins()->each(function ($plugin) {
            $pluginInstance = app($plugin);
            if (method_exists($pluginInstance, 'authorize')) {
                if ($pluginInstance->authorize() === false) {
                    return false;
                }
            }
        });
    }

    public static function getPlugins()
    {
        return app(PluginsManager::class)->getPlugins(static::class);
    }

    public static function setPlugins($plugins)
    {
        static::$plugins = $plugins;
    }
}