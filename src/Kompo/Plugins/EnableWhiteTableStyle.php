<?php

namespace Condoedge\Utils\Kompo\Plugins;

class EnableWhiteTableStyle extends \Condoedge\Utils\Kompo\Plugins\Base\ComponentPlugin
{
    public function onBoot()
    {
        if ($this->getComponentProperty('isWhiteTable') !== true) {
            return;
        }

        $this->prependComponentProperty('itemsWrapperClass', ' bg-white rounded-2xl border-2 border-gray-300 WhiteTable ');
    }
}