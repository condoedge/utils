<?php

namespace Condoedge\Utils\Kompo\Plugins\Base;

use Condoedge\Utils\Kompo\Plugins\Base\PluginsManager;

trait HasComponentPlugins
{
    protected static $globalPlugins = [];


    protected function getManagableMethods()
    {
        return collect($this->getAllPlugins())->map(function ($plugin) {
            $pluginInstance = is_object($plugin) ? $plugin : new $plugin($this);
            if (method_exists($pluginInstance, 'managableMethods')) {
                return $pluginInstance->managableMethods();
            }
        })->flatten()->unique()->all();
    }

    public function booted()
    {
        $this->getAllPlugins()->each(function ($plugin) {
            $pluginInstance = is_object($plugin) ? $plugin : new $plugin($this);
            if (method_exists($pluginInstance, 'onBoot')) {
                $pluginInstance->onBoot();
            }
        });
    }

    public function __call($method, $args)
    {
        foreach ($this->getAllPlugins() as $plugin) {
            $pluginInstance = is_object($plugin) ? $plugin : new $plugin($this);
            if (method_exists($pluginInstance, 'managableMethods')) {
                $methods = $pluginInstance->managableMethods();
                if (in_array($method, $methods)) {
                    return $pluginInstance->$method(...$args);
                }
            }
        }

        return is_callable(['parent', '__call']) ? parent::__call($method, $args) : null;
    }

    public function pluginMethod()
    {
        $method = request('method');

        if (!$method) {
            throw new \Exception('Method not found in request.');
        }

        if (!in_array($method, $this->getManagableMethods())) {
            throw new \Exception('Method not found in plugin.');
        }

        return $this->$method();
    }

    public function authorize()
    {
        foreach ($this->getAllPlugins() as $plugin) {
            $pluginInstance = is_object($plugin) ? $plugin : new $plugin($this);
            if (method_exists($pluginInstance, 'authorize') && $pluginInstance->authorize() === false) {
                return false;
            }
        }

        return true;
    }

    public function getAllPlugins()
    {
        return collect(array_merge($this->getLocalPlugins(), app(PluginsManager::class)->getPlugins(static::class)));
    }

    public static function setPlugins($plugins, $override = false)
    {
        if ($override) {
            static::$globalPlugins = $plugins;
            return;
        }

        static::$globalPlugins = array_merge(static::$globalPlugins ?? [], $plugins);
    }

    public static function getGlobalPlugins()
    {
        return static::$globalPlugins ?? [];
    }

    public function getLocalPlugins()
    {
        if (property_exists($this, 'plugins')) {
            return $this->plugins ?? [];
        }

        return [];
    }

    public static function hasMergePluginsWithParents()
    {
        return static::$mergePluginsWithParents ?? true;
    }
}