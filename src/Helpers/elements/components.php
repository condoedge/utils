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

function _SwipeableTabs()
{
    return (\Condoedge\Utils\Kompo\Elements\SwipeableTabs::form(...func_get_args()))->swipeable();
}

function _SwipeableTab()
{
    return \Condoedge\Utils\Kompo\Elements\SwipeableTab::form(...func_get_args());
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

function _SignaturePad()
{
    return \Condoedge\Utils\Kompo\Elements\SignaturePad::form(...func_get_args());
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

if (!function_exists('_ApexChart')) {
    /**
     * Render an ApexCharts chart inside a Kompo element.
     *
     * Requires the VlApexChart Vue component from condoedge/js-kompo-utils to be
     * registered in the host app (auto-registered via getAllDefaultComponents()).
     *
     * @param  array $chartOptions  Raw ApexCharts options (chart, series, xaxis, etc.)
     * @return \Condoedge\Utils\Kompo\Elements\ApexChart
     */
    function _ApexChart($chartOptions = [])
    {
        return \Condoedge\Utils\Kompo\Elements\ApexChart::form()
            ->config(['chartOptions' => $chartOptions]);
    }
}