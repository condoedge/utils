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

    /** Replaces the tab labels with dots under the content, plus an optional Next button beside them. */
    public function dots($nextLabel = null)
    {
        return $this->config([
            'dots' => true,
            'nextLabel' => $nextLabel ? __($nextLabel) : null,
        ]);
    }

    /** Left/right arrows change tab, except while typing in a field. */
    public function arrowKeys($value = true)
    {
        return $this->config([
            'arrowKeys' => $value,
        ]);
    }

    public function tabParamKey($key = 'tab_number')
    {
        return $this->config([
            'tabParamKey' => $key,
        ]);
    }
}