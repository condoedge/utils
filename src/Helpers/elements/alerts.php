<?php

function _WarningAlert($label)
{
    return _Html($label)->class('bg-warning bg-opacity-40 border-warning border-2 text-center rounded-xl py-3 px-2')->class('mb-4');
}

function _CleanErrorField()
{
    return _ErrorField()->noInputWrapper()->name('error_field', false)->class('!text-base !p-0 !m-0 !border-0 [&>.vlFieldErrors]:relative [&>.vlFieldErrors]:-top-3 [&>.vlFieldErrors]:!p-0 [&>.vlFieldErrors]:!text-base');
}

function _WarningBanner($title, $subtitle = null) {
    return _Flex(
        _Sax('danger', 28)->class('!text-warningdark'),
        _Rows(
            _Html($title)->class('!text-warningdark font-medium text-lg')->class($subtitle ? 'mb-1' : ''),
            _Html($subtitle)->class('!text-warningdark'),
        ),
    )->class('flex gap-6 bg-warning/10 border-warning border p-4 rounded-lg !pr-2');
}