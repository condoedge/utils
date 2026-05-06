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

    public function tabParamKey($key = 'tab_number')
    {
        return $this->config([
            'tabParamKey' => $key,
        ]);
    }
}