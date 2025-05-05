<?php
namespace Condoedge\Utils\Kompo\Plugins;

use Condoedge\Utils\Kompo\Plugins\Base\ComponentPlugin;

class HasScroll extends ComponentPlugin
{
    public function onBoot()
    {
        $this->prependComponentProperty('class', ' overflow-y-auto mini-scroll ');
        $this->prependComponentProperty('style', ' max-height: 95vh; ');
    }
}