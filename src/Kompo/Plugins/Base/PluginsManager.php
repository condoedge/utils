<?php

namespace Condoedge\Utils\Kompo\Plugins;

class PluginsManager
{
    public function getPlugins($className)
    {
        if (property_exists($className, 'plugins')) {
            $mergeWithParents = !property_exists($className, 'mergePluginsWithParents') ? false : $className::$mergePluginsWithParents;
            
            return !$mergeWithParents ? $className::$plugins :
                array_merge($className::$plugins, $this->getPlugins(class_parents($className)));
        }
    }
}