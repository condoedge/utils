<?php

use Condoedge\Utils\Kompo\Plugins\Base\ComponentPlugin;

class StylesPlugin extends ComponentPlugin
{
    protected $class;
    protected $style;
    protected $itemsWrapperClass;

    public function onBoot()
    {
        $this->prependComponentProperty('class', $this->class);
        $this->prependComponentProperty('style', $this->style);
        $this->prependComponentProperty('itemsWrapperClass', $this->itemsWrapperClass);
    }

    public function class($class)
    {
        $this->class = $class;
        return $this;
    }

    public function style($style)
    {
        $this->style = $style;
        return $this;
    }

    public function itemsWrapperClass($class)
    {
        $this->itemsWrapperClass = $class;
        return $this;
    }
}