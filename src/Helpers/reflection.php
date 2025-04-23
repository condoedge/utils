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