<?php

if(!function_exists('fileRoute')) {
	function fileRoute($type, $id)
	{
		return route('files.display', ['type' => $type, 'id' => $id]);
	}
}

if(!function_exists('refresh')) {
function refresh()
	{
		return redirect()->back();
	}
}


if(!function_exists('getReferrerRoute')) {
	function getReferrerRoute()
	{
		return app('router')->getRoutes()->match(app('request')->create(url()->previous()))->getName();
	}
}

if(!function_exists('getCurrentComponentPage')) {
	function getCurrentComponentPage()
	{
		if (!request()->route()) {
			return null;
		}

		return request()->route()->controller::class;
	}
}

if (!function_exists('safeRedirectPath')) {
	function safeRedirectPath(?string $path): ?string
	{
		if (!url()->isValidUrl($path)) {
			return route('dashboard');
		}

		$targetHost = parse_url($path, PHP_URL_HOST);
		$targetScheme = parse_url($path, PHP_URL_SCHEME);

		if ($targetHost !== request()->getHost()) {
			return route('dashboard');
		}

		if (! in_array($targetScheme, ['http', 'https'], true)) {
			return route('dashboard');
		}

		return $path;
	}
}