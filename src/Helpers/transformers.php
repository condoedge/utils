<?php

/* Transformers */
if (!function_exists('replaceAccents')) {
	function replaceAccents($str)
	{
		$unwanted_array = array(    'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E','Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U','Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c','è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o','ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y' );

		return strtr( $str, $unwanted_array );
	}
}

if (!function_exists('prepareForSearch')) {
	function prepareForSearch($str)
	{
		return replaceAccents(str_replace(array("\r", "\n", "\t", "  ", "&nbsp;"), ' ',strip_tags(preg_replace('/<\s*style.+?<\s*\/\s*style.*?>/si', ' ', $str ))));
	}
}

if (!function_exists('tinyintToBool')) {
	function tinyintToBool($value): string
	{
		return $value == 1 ? 'Yes' : 'No';
	}
}

if (!function_exists('toRounded')) {
	function toRounded($value, $decimals = 2): string
	{
		return round($value, $decimals);
	}
}

if (!function_exists('getFullName')) {
	function getFullName($firstName, $lastName): string
	{
		return collect([$firstName, $lastName])->filter()->implode(' ');
	}
}

if (!function_exists('guessFirstName')) {
	function guessFirstName($fullName)
	{
		$names = explodeName($fullName);
		return count($names) == 1 ? '' : $names[0];
	}
}

if (!function_exists('guessLastName')) {
	function guessLastName($fullName)
	{
		$names = explodeName($fullName);
		return count($names) == 1 ? $names[0] : $names[1];
	}
}

if (!function_exists('explodeName')) {
	function explodeName($fullName)
	{
		return explode(' ', $fullName, 2);
	}
}

if (!function_exists('getAgeFromDob')) {
	function getAgeFromDob($dateOfBirth): string
	{
		if (!$dateOfBirth) {
			return '';
		}

		return carbonNow()->diffInYears(carbon($dateOfBirth));
	}
}

if (!function_exists('sizeAsKb')) {
	function sizeAsKb($size)
	{
		return round($size / 1024, 2) . ' KB';
	}
}

if (!function_exists('camelToSnake')) {
	function camelToSnake($camelCase) { 
		$pattern = '/(?<=\\w)(?=[A-Z])|(?<=[a-z])(?=[0-9])/'; 
		$snakeCase = preg_replace($pattern, '_', $camelCase); 
		return strtolower($snakeCase); 
	} 
}
