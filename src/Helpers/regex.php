<?php

if (!function_exists('isHttpsLink')) {
    function isHttpsLink($text)
    {
        return preg_match('/^https:\/\//', $text);
    }
}

if (!function_exists('domainRegex')) {
    function domainRegex($text)
    {
        return preg_match('/@(.*)/', $text, $matches) ? $matches[1] : null;
    }
}