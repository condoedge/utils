<?php

function _WarningAlert($label)
{
    return _Html($label)->class('bg-warning bg-opacity-40 border-warning border-2 text-center rounded-xl py-3 px-2')->class('mb-4');
}