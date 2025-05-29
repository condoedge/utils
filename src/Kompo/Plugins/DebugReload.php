<?php

namespace Condoedge\Utils\Kompo\Plugins;

use Condoedge\Utils\Kompo\Plugins\Base\ComponentPlugin;

class DebugReload extends ComponentPlugin
{
    public function onBoot()
    {
        if (!config('app.debug') || env('DISABLE_DEBUG_RELOAD', false)) {
            return;
        }

        $this->component->elements = array_merge($this->component->elements, [
            _Link()->icon(_Sax('refresh'))->refresh()->class('absolute top-6 right-6')
        ]);

        $this->prependComponentProperty('class', ' relative ');
    }
}