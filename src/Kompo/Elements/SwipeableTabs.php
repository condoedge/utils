<?php

namespace Condoedge\Utils\Kompo\Elements;

class SwipeableTabs extends \Kompo\Tabs
{
    public $vueComponent = 'SwipeableTabs';

    public function swipeable($value = true)
    {
        return $this->config([
            'swipeable' => $value,
        ]);
    }
}