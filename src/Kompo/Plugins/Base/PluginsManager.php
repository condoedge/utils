<?php

namespace Condoedge\Utils\Kompo\Plugins\Base;

class PluginsManager
{
    public function getPlugins($className)
    {
        if (!method_exists($className, 'getGlobalPlugins') || !method_exists($className, 'hasMergePluginsWithParents')) {
            return [];
        }

        $mergeWithParents = $className::hasMergePluginsWithParents();
        $globalPlugins = $className::getGlobalPlugins();

        $parentClass = get_parent_class($className);
        if (!$mergeWithParents || !$parentClass) {
            return $globalPlugins;
        }

        return array_merge($globalPlugins, $this->getPlugins($parentClass));
    }
}
