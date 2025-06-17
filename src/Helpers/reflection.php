<?php

function getPrivateProperty($object, $property)
{
    $reflect = new \ReflectionClass($object);

    if ($reflect->hasProperty($property)) {
        $prop = $reflect->getProperty($property);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }

    if (property_exists($object, $property)) {
        // If the property exists but is not private, return its value
        return $object->{$property};
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

function setPrivateProperty($object, $property, $value)
{
    $reflect = new \ReflectionClass($object);

    if ($reflect->hasProperty($property)) {
        $prop = $reflect->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }

    if (property_exists($object, $property)) {
        // If the property exists but is not private, set its value
        $object->{$property} = $value;
    }
}

function unsetPrivateProperty($object, $property)
{
    $reflect = new \ReflectionClass($object);

    if ($reflect->hasProperty($property)) {
        $prop = $reflect->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, null);
    }
}

function setPrivatePropertyIncludingParent($object, $property, $value)
{
    $reflection = new \ReflectionClass($object);
    $currentClass = $reflection;

    while ($currentClass) {
        if ($currentClass->hasProperty($property)) {
            $prop = $currentClass->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue($object, $value);
            return;
        }
        $currentClass = $currentClass->getParentClass();
    }

    // If we reach here, the property was not found in the class hierarchy
    throw new \Exception("Property '$property' not found in class hierarchy.");
}

function getAllPropertiesIncludingParent($object): array
{
    $reflection = new \ReflectionClass($object);

    $allProperties = [];
    $currentClass = $reflection;

    while ($currentClass) {
        foreach ($currentClass->getProperties() as $property) {
            $property->setAccessible(true);
            
            try {
                if ($property->isInitialized($object)) {
                    $allProperties[$property->getName()] = $property->getValue($object);
                }
            } catch (\Error $e) {
                // If the property is not accessible or not initialized, we skip it
                continue;
            }
        }

        $currentClass = $currentClass->getParentClass();
    }

    return $allProperties;
}

function findClosures($object, $path = '', array &$visited = []): array {
    $closures = [];

    if (is_object($object)) {
        $objId = spl_object_id($object);
        if (isset($visited[$objId])) {
            return [];
        }
        $visited[$objId] = true;

        $reflection = new ReflectionObject($object);
        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $property->setAccessible(true);
            
            try {
                $value = $property->getValue($object);
            } catch (Error $e) {
                continue;
            }

            $currentPath = $path . '->' . $property->getName();

            if ($value instanceof Closure) {
                $closures[] = $currentPath;
            } elseif (is_object($value) || is_array($value)) {
                $closures = array_merge($closures, findClosures($value, $currentPath, $visited));
            }
        }
    } elseif (is_array($object)) {
        foreach ($object as $key => $value) {
            $currentPath = $path . "['$key']";
            if ($value instanceof Closure) {
                $closures[] = $currentPath;
            } elseif (is_object($value) || is_array($value)) {
                $closures = array_merge($closures, findClosures($value, $currentPath, $visited));
            }
        }
    }

    return $closures;
}

function cleanClosuresFromObject(&$object, array &$visited = []): void {
    if (!is_object($object)) {
        return;
    }

    $objId = spl_object_id($object);
    if (isset($visited[$objId])) {
        return; // Prevent circular references
    }
    $visited[$objId] = true;

    $reflection = new ReflectionObject($object);

    foreach ($reflection->getProperties() as $property) {
        if ($property->isStatic()) continue; // Skip static properties

        $property->setAccessible(true);

        try {
            $value = $property->getValue($object);
        } catch (Error $e) {
            continue; // Skip unreadable properties
        }

        if ($value instanceof Closure) {
            // ✅ Remove the closure by setting the property to null
            $property->setValue($object, null);
        } elseif (is_object($value)) {
            // ✅ Recursively clean nested objects
            cleanClosuresFromObject($value, $visited);
        } elseif (is_array($value)) {
            // ✅ Clean array and overwrite property with cleaned version
            $cleanedArray = exportArrayWithoutClosures($value, $visited);
            $property->setValue($object, $cleanedArray);
        }
    }
}

function exportArrayWithoutClosures(array $data, array &$visited = []): array {
    $result = [];

    foreach ($data as $key => $value) {
        if ($value instanceof Closure) {
            continue; // ✅ Skip closures
        } elseif (is_object($value)) {
            cleanClosuresFromObject($value, $visited);
            $result[$key] = $value; // Keep the cleaned object
        } elseif (is_array($value)) {
            $result[$key] = exportArrayWithoutClosures($value, $visited); // Clean nested arrays
        } else {
            $result[$key] = $value; // Keep primitives as is
        }
    }

    return $result;
}
