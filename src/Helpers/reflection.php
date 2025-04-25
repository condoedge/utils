<?php

function getPrivateProperty($object, $property)
{
    $reflect = new \ReflectionClass($object);

    if ($reflect->hasProperty($property)) {
        $prop = $reflect->getProperty($property);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }

    return null;
}

function callPrivateMethod($object, $method, ...$args)
{
    $reflect = new \ReflectionClass($object);

    if ($reflect->hasMethod($method)) {
        $method = $reflect->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }

    return null;
}