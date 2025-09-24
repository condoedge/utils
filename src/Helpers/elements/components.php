<?php

use Condoedge\Utils\Kompo\Elements\Collapsible;
use Condoedge\Utils\Kompo\Elements\ResponsiveTabs;
use Condoedge\Utils\Kompo\Elements\InternationalPhoneInput;
use Condoedge\Utils\Kompo\Elements\PasswordInput;

if (!function_exists('_Collapsible')) {
    function _Collapsible() {
        return Collapsible::form(...func_get_args());
    }
}


if (!function_exists('_ResponsiveTabs')) {
    /**
     * Tabs element with a select dropdown for mobile.
     */
    function _ResponsiveTabs()
    {
        return ResponsiveTabs::form(...func_get_args());
    }
}

if (!function_exists('_ValidatedInput')) {
    function _ValidatedInput()
    {
        return \Condoedge\Utils\Kompo\Elements\ValidatedInput::form(...func_get_args());
    }
}


function _CreditCardInput()
{
    return \Condoedge\Utils\Kompo\Elements\CreditCardInput::form(...func_get_args());
}

function _DateTextInput()
{
    return \Condoedge\Utils\Kompo\Elements\DateTextInput::form(...func_get_args());
}

function _Text()
{
    return \Condoedge\Utils\Kompo\Elements\Text::form(...func_get_args());
}

if (!function_exists('_InternationalPhoneInput')) {
    function _InternationalPhoneInput()
    {
        return InternationalPhoneInput::form(...func_get_args());
    }
}

if (!function_exists('_PasswordInput')) {
    function _PasswordInput()
    {
        return PasswordInput::form(...func_get_args());
    }
}