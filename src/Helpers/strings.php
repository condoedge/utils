<?php

if (!function_exists('plainText')) {
	/** Editor HTML as plain text: a line per paragraph, list item or line break, entities decoded. */
	function plainText(?string $html): string
	{
		$text = preg_replace('~<br\b[^>]*>|</(?:p|div|li|tr|h[1-6])\s*>~i', "\n", (string) $html);
		$text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = preg_replace('/[^\S\n]+/u', ' ', $text) ?? $text;

		return trim(preg_replace(['/ ?\n ?/', '/\n{3,}/'], ["\n", "\n\n"], $text));
	}
}

if (!function_exists('plainTextLine')) {
	function plainTextLine(?string $html): string
	{
		return preg_replace('/\n+/', ' ', plainText($html));
	}
}

if (!function_exists('isBlankHtml')) {
	/** True when the HTML shows no text: empty paragraphs, line breaks and non-breaking spaces only. */
	function isBlankHtml(?string $html): bool
	{
		return plainText($html) === '';
	}
}
