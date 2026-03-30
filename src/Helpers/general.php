<?php

use Condoedge\Utils\Facades\UserModel;
use Condoedge\Utils\Models\Traits\HasUserSettings;
use Illuminate\Support\Str;
use Kompo\Elements\Element;

if (!function_exists('getAppClass')) {
	function getAppClass(...$namespaces)
	{
        foreach ($namespaces as $namespace) {
            if ($namespace && class_exists($namespace)) {
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

if (!function_exists('componentIntroViewedKey')) {
	function componentIntroViewedKey($component)
	{
		$componentName = Str::slug(camelToSnake(class_basename($component)));
		return $componentName . '_intro_viewed';
	}
}

if (!function_exists('componentIntroViewed')) {
	function componentIntroViewed($component)
	{
		$componentKey = componentIntroViewedKey($component);

		if (!in_array(HasUserSettings::class, class_uses(UserModel::getClass()))) {
			\Log::error('The component must implement HasUserSettings trait to use componentIntroViewed function. If you are not using it you can disable the plugin using `excludePlugins` method');
			return false;
		}

		return auth()->user()?->getSettingValue($componentKey);
	}
}

if (!function_exists('getTutorialAnimationButton')) {
	function getTutorialAnimationButton($iconSize = 30)
	{
		return _Link()->icon(_Sax('message-question', $iconSize))->class('text-gray-800 text-2xl')
			->initTutorial('default');
	}
}

if (!function_exists('_Tutorial')) {
	/**
	 * Trigger a tutorial on page load. Used for cross-page redirect chaining
	 * or explicit tutorial placement in component render().
	 */
	function _Tutorial(string $tutorialName)
	{
		// Cross-page chaining: URL param forces start regardless of viewed status
		$fromUrl = request('tutorial');
		$fromStep = (int) request('fromStep', 0);

		if ($fromUrl === $tutorialName) {
			return _Hidden()->onLoad(fn($e) => $e->run(
				"() => window.TutorialEngine.start('{$tutorialName}', {$fromStep})"
			));
		}

		$key = \Illuminate\Support\Str::slug($tutorialName) . '_tutorial_viewed';

		if (auth()->user()?->getSettingValue($key)) {
			return null;
		}

		auth()->user()?->saveSetting($key, true);

		return _Hidden()->onLoad(fn($e) => $e->run(
			"() => window.TutorialEngine.start('{$tutorialName}')"
		));
	}
}

if (!function_exists('_StepBuilderContainer')) {
	function _StepBuilderContainer()
	{
		return \Condoedge\Utils\Kompo\Elements\TutorialStepBuilder::renderIfDebug();
	}
}

if (!function_exists('secureCall')) {
	function secureCall($functionName, ...$params)
	{
		try {
			if (function_exists($functionName)) {
				return $functionName(...$params);
			} elseif (isset($params[0]) && method_exists($params[0], $functionName)) {
				$object = array_shift($params);
				return $object->$functionName(...$params);
			} 
		} catch (\Exception $e) {
			\Log::error('Error in secureCall: ' . $e->getMessage());
			return null;
		}
	}
}