<?php

use \Kompo\Elements\Element;

/* GENERAL SHORTCUTS */
Element::macro('mb4', fn() => $this->class('mb-4'));
Element::macro('mb2', fn() => $this->class('mb-2'));
Element::macro('p4', fn() => $this->class('p-4'));

/* GENERAL CARD SETTINGS */
Element::macro('scrollY', fn($height = '300px') => $this->class('overflow-y-auto mini-scroll')->style('min-height:'.$height));

\Kompo\Elements\Element::macro('pageTitle', function($color = 'text-level1'){
    return $this->class('font-semibold text-4xl')->class($color);
});

\Kompo\Elements\Element::macro('medTitle', function($color = 'text-level3'){
    return $this->class('font-normal text-2xl')->class($color);
});

// You need to specify name if you want to use before name method
\Kompo\Elements\Field::macro('checkColor', function($color, $uncheckedColor = 'var(--form-control-border-color)', $name = null){
    $js = '() => {setTimeout(() => checkChangeMyColor("' . $color . '","' . $uncheckedColor . '","' . ($name ?: $this->name) . '"), 100)}';

    // opacity: 0 is to avoid the toggle to be visible before the JS is loaded
    return $this->class('opacity-0')->onLoad->run($js)->onChange->run($js);
});

\Kompo\Toggle::macro('dotColor', function($color, $name = null){
    $js = '() => {setTimeout(() => changeDotColor("' . $color .'","' . ($name ?: $this->name) . '"), 100)}';

    // opacity: 0 is to avoid the toggle to be visible before the JS is loaded
    return $this->class('opacity-0')->onLoad->run($js);
});


\Kompo\Elements\Element::macro('bigOnMobile', function(){
    return $this->class('!text-lg !font-medium');
});

\Kompo\Elements\Element::macro('miniTitle', function(){
    return $this->class('text-sm uppercase leading-widest font-bold');
});

\Kompo\Input::macro('inputBigOnMobile', function(){
    return $this->class('!text-xl [&>.vlFormLabel>span]:!text-base [&>.vlInputWrapper>input]:!p-4');
});

if (!function_exists('_Spinner')) {
    function _Spinner($size = 'w-7 h-7', $color = 'text-level1')
    {
        return _Html('
            <svg class="animate-spin '. implode(' ', [$size, $color]) . '" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        ');
    }
}

if (!function_exists('_MiniTitle')) {
    function _MiniTitle($label)
    {
        return _Html($label)->miniTitle();
    }
}

if (!function_exists('_PageTitle')) {
    function _PageTitle($label)
    {
        return _H1($label)->class('text-2xl sm:text-3xl font-bold');
    }
}

function _ChatCount($count)
{
    return _Html($count)
        ->icon(
            _Sax('message',20)->class('text-xl')
        )
        ->class('flex items-center');
}

function _DiffDate($date)
{
    if(!$date)
        return;

    return _Html($date->diffForHumans())->class('text-xs text-gray-600 whitespace-nowrap');
}

function _DateLockErrorField()
{
    return _ErrorField()->name('not_editable', false);
}

function _TotalCurrencyCols($title, $id, $amount = 0, $border = true)
{
    return _Columns(
        _Html($title)->class('text-level1 font-medium'),
        _Currency($amount)->id($id)->class('ccy-amount text-lg text-level1')
    )->class('px-4 py-2 text-right font-semibold'.($border ? '': ' -mt-4'));
}

function _MiniLabelDate($label, $date, $class = '')
{
    return _Rows(
        _MiniLabel($label),
        _HtmlDate($date)->class($class),
    );
}

function _MiniLabelCcy($label, $date, $class = '')
{
    return _Rows(
        _MiniLabel($label),
        _Currency($date)->class($class),
    );
}

function _MiniLabelValue($label, $value)
{
    return _Rows(
        _MiniLabel($label),
        _Html($value ?: '&nbsp;'), //when empty preserve height
    );
}

function _MiniLabel($label)
{
    return _Html($label)->class('text-level1 opacity-60 text-xs');
}

if (!function_exists('_ModalHeader')) {
    function _ModalHeader($els)
    {
        return _FlexBetween(
            $els,
        )
            ->class('px-8 pt-6 pb-4 rounded-t-2xl')
            ->class('flex-col items-start md:flex-row md:items-center')
            ->alignStart();
    }
}