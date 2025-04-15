<?php

use Kompo\Elements\Element;

if (!function_exists('getAppClass')) {
	function getAppClass(...$namespaces)
	{
        foreach ($namespaces as $namespace) {
            if (class_exists($namespace)) {
                return $namespace;
            }
        }

        return null;
	}
}

if (!function_exists('all_class_uses')) {
	function all_class_uses($model)
	{
		$class = new ReflectionClass($model);
		$traits = $class->getTraits();
		while($parent = $class->getParentClass()) {
			$traits += $class->getTraits();
			$class = $parent;
		}
		return array_combine(array_keys($traits), array_keys($traits));
	}
}

if (!function_exists('safeTruncate')) {
	function safeTruncate($text, $nbChars = 90)
	{
		return mb_substr(strip_tags($text), 0, $nbChars);
	}
}


/* URIS */
if (!function_exists('createRandomNumber')) {
	function createRandomNumber($max)
	{
		return random_int(0, $max - 1);
	}
}

if (!function_exists('getRandStringForModel')) {
	function getRandStringForModel($model, $colName, $length = 9)
	{
		if(!$model->$colName) {
			$code = '';
			do {
				$code = \Str::random($length);
			} while($model::class::where($colName, $code)->first());
	
			return $code;
		}
	
		return $model->$colName;
	}
}

if (!function_exists('objectToArray')) {
	function objectToArray($object)
	{
		return json_decode(json_encode($object), true);
	}
}

/* GENERAL KOMPO */
if (!function_exists('isKompoEl')) {
	function isKompoEl($el)
	{
		return $el instanceof Element;
	}
}