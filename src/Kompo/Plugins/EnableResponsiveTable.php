<?php

namespace Condoedge\Utils\Kompo\Plugins;

use Kompo\Elements\Layout;
use Kompo\Th;

class EnableResponsiveTable extends \Condoedge\Utils\Kompo\Plugins\Base\ComponentPlugin
{
    protected function isDisabled()
    {
        return $this->getComponentProperty('isResponsive') === false
            || !$this->componentHasMethod('headers');
    }

    public function onBoot()
    {
        if ($this->isDisabled()) {
            return;
        }

        $this->prependComponentProperty('class', ' responsive-table ');
        
        $decoratedRows = collect(getPrivateProperty($this->getComponentProperty('query'), 'items'))->map(function ($item) {
            return [
                ...$item,
                'render' => $this->decorateRow($item['render'])
            ];
        });

        setPrivateProperty($this->getComponentProperty('query'), 'items', $decoratedRows);
    }

    public function decorateRow($row)
    {
        $wrapper = ($row instanceof Layout) ? $row::class : null;
        $elements = $wrapper ? $row->elements : $row;

        $decoratedElements = collect($elements)->map(function ($element, $i) {
            return _Rows(
                _FlexBetween(
                    !is_string($this->getHeader($i)) ? $this->getHeader($i) 
                        : _Html($this->getHeader($i))->class('uppercase font-bold'),
                    $element,
                )->class('flex md:hidden'),
                _Rows($element)->class('hidden md:block')
            );
        });

        return $wrapper ? $wrapper::form($decoratedElements) : $decoratedElements;
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