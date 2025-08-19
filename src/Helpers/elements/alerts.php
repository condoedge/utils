<?php

function _WarningAlert($label)
{
    return _Html($label)->class('bg-warning bg-opacity-40 border-warning border-2 text-center rounded-xl py-3 px-2')->class('mb-4');
}

function _CleanErrorField()
{
    return _ErrorField()->noInputWrapper()->name('error_field', false)->class('!text-base !p-0 !m-0 !border-0 [&>.vlFieldErrors]:relative [&>.vlFieldErrors]:-top-3 [&>.vlFieldErrors]:!p-0 [&>.vlFieldErrors]:!text-base');
}