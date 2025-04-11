<?php

namespace Condoedge\Utils\Kompo\Plugins\Base;

abstract class ComponentPlugin
{
    protected $component;

    public function __construct($component = null)
    {
        $this->component = $component;
    }

    public function onBoot() 
    {
        throw new \Exception('You must implement the beforeBoot method in your plugin class.');
    }

    public function managableMethods()
    {
        return [];
    }

    public function authorize()
    {
        return true;
    }
}