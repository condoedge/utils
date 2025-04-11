<?php

function isHttpsLink($text)
{
    return preg_match('/^https:\/\//', $text);
}

function domainRegex($text)
{
    return preg_match('/@(.*)/', $text, $matches) ? $matches[1] : null;
}