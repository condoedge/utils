<?php

namespace Condoedge\Utils\Kompo\Elements;

use Kompo\Elements\Element;

class TutorialStepBuilder extends Element
{
    public $vueComponent = 'TutorialStepBuilder';

    public static function renderIfDebug()
    {
        if (!config('app.debug')) {
            return null;
        }

        return new static();
    }
}
