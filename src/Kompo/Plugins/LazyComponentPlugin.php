<?php

namespace Condoedge\Utils\Kompo\Plugins;

use Condoedge\Utils\Kompo\Plugins\Base\ComponentPlugin;
use Condoedge\Utils\Services\LazyComponent\LazyComponentRegistry;

class LazyComponentPlugin extends ComponentPlugin
{
    public function onBoot()
    {
        if (LazyComponentRegistry::hasPendingBatches()) {
            $this->appendComponentProperty('elements', LazyComponentRegistry::getBatchCoordinators());
        }

        LazyComponentRegistry::resetCounters();
    }
}
