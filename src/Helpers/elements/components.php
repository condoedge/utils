<?php

use Condoedge\Utils\Kompo\Elements\Collapsible;
use Condoedge\Utils\Kompo\Elements\ResponsiveTabs;

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
        return \Kompo\Auth\Elements\ValidatedInput::form(...func_get_args());
    }
}
