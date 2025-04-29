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

    public function getComponentProperty($property)
    {
        return getPrivateProperty($this->component, $property);
    }

    public function setComponentProperty($property, $value)
    {
        $reflect = new \ReflectionClass($this->component);

        if ($reflect->hasProperty($property)) {
            $prop = $reflect->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue($this->component, $value);
        } else {
            // Add the property if it doesn't exist as public
            $this->component->{$property} = $value;
        }
    }

    public function prependComponentProperty($property, $value)
    {
        $currentValue = $this->getComponentProperty($property);

        $type = gettype($currentValue);
        
        if ($type === 'array') {
            $newValue = array_merge((array)$value, (array)$currentValue);
        } elseif ($type === 'string') {
            $newValue = $value . $currentValue;
        } else {
            $newValue = $value;
        }

        $this->setComponentProperty($property, $newValue);
    }

    /**
     * Append a value to a component property.
     *
     * @param string $property The name of the property.
     * @param mixed $value The value to append.
     */
    public function appendComponentProperty($property, $value)
    {
        $currentValue = $this->getComponentProperty($property);
        $newValue = array_merge((array)$currentValue, (array)$value);
        $this->setComponentProperty($property, $newValue);
    }

    public function componentHasMethod($method)
    {
        $reflect = new \ReflectionClass($this->component);

        return $reflect->hasMethod($method);
    }

    public function callComponentMethod($method, ...$args)
    {
        if ($this->componentHasMethod($method)) {
            $reflect = new \ReflectionClass($this->component);
            $method = $reflect->getMethod($method);
            $method->setAccessible(true);
            return $method->invokeArgs($this->component, $args);
        }

        throw new \Exception("Method {$method} not found in component.");
    }
}