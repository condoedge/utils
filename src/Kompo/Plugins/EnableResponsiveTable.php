<?php

namespace Condoedge\Utils\Kompo\Plugins;

use Kompo\Elements\Layout;
use Kompo\Th;

class EnableResponsiveTable extends \Condoedge\Utils\Kompo\Plugins\Base\ComponentPlugin
{
    protected function isDisabled()
    {
        return $this->getComponentProperty('isResponsive') !== true
            || !$this->componentHasMethod('headers');
    }

    public function onBoot()
    {
        if ($this->isDisabled()) {
            return;
        }

        $this->prependComponentProperty('class', ' responsive-table ');
        
        $this->makeRowsResponsive($this->getComponentProperty('query'));
    }

    public function onAfterKompoAction($actionType, $content = null)
    {
        if ($this->isDisabled()) {
            return $content;
        }

        if ($actionType != 'browse-items') {
            return $content;
        }

        return $this->makeRowsResponsive($content);
    }

    protected function makeRowsResponsive($query = null)
    {
        $decoratedRows = collect(getPrivateProperty($query, 'items'))->map(function ($item) {
            return [
                ...$item,
                'render' => $this->decorateRow($item['render'])
            ];
        });

        setPrivateProperty($query, 'items', $decoratedRows);

        return $query;
    }

    public function decorateRow($row)
    {
        $wrapper = ($row instanceof Layout) ? $row::class : null;
        $elements = $wrapper ? $row->elements : $row;
        // Cleaning indexes to avoid issues when mapping elements with headers
        $elements = array_values($elements);

        $decoratedElements = collect($elements)->map(function ($element, $i) {
            return _Rows(
                _FlexBetween(
                    !is_string($this->getHeader($i)) ? $this->getHeader($i) 
                        : _Html('<span>'.$this->getHeader($i).'</span>')->class('uppercase font-bold'),
                    $element,
                )->class('flex md:hidden'),
                _Rows($element)->class('hidden md:block')
            );
        });

        if ($wrapper) {
            setPrivateProperty($row, 'elements', $decoratedElements->toArray());
            setPrivateProperty($row, 'style', trim((getPrivateProperty($row, 'style') ?? ''). ' height: auto !important;'));

            return $row;
        }

        return $decoratedElements;
    }

    protected function getHeader($index)
    {
        $headers = $this->callComponentMethod('headers');

        if (!is_array($headers)) {
            return '';
        }

        $header = $headers[$index] ?? '';

        return ($header instanceof Th) ? $header->label : $header;
    }
}