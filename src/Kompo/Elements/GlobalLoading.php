<?php

namespace Condoedge\Utils\Kompo\Elements;

/**
 * Id of the full-screen loading host rendered by the app's `kompo.scripts` blade.
 * Pass it to ->withLoadingIn() to show the overlay for the duration of an ajax action.
 */
final class GlobalLoading
{
    public const PANEL_ID = 'vl-global-loading';
}
