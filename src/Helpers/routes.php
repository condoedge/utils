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