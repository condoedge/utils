<?php

/* URIS */
if (!function_exists('getPushParameterFn')) {
	function getPushParameterFn($parameter, $value, $valueInJs = false)
	{
		$getActualTabFn = <<<javascript
			function getActualTab(id = null)
			{
				return [...document.querySelector((id ? '#' + id + ' ' : '') + ".vlTabContent").children].findIndex((c) => !c.getAttribute("aria-hidden"));
			}
		javascript;

		$regexParam = "/(&|\?)$parameter=[^&]*/";

		if ($valueInJs) {
			$value = '${' . $value . '}';
		}

		$fn = '() => {' . $getActualTabFn . ' const hrefWithoutActualParam = location.href.replace(' . $regexParam . ', ""); const charToAppend = hrefWithoutActualParam.indexOf("?") == -1 ? "?" : "&"; window.history.pushState(null, null, `${hrefWithoutActualParam}${charToAppend}'
			. $parameter . '=' . $value . '`);}';

		return $fn;
	}
}