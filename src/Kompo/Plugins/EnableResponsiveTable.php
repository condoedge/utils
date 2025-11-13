<?php

namespace Condoedge\Utils\Kompo\Plugins;

use Kompo\Elements\Layout;
use Kompo\Th;

class EnableResponsiveTable extends \Condoedge\Utils\Kompo\Plugins\Base\ComponentPlugin
{
    public function onBoot()
    {
        if ($this->getComponentProperty('isResponsive') !== true) {
            return;
        }

        $this->prependComponentProperty('class', 'responsive-table');
    }

    public function managableMethods()
    {
        return [
            'decorateRow',
        ];
    }

    public function decorateRow($row)
    {
        $wrapper = ($row instanceof Layout) ? $row::class : null;
        $elements = $wrapper ? $row->elements : $row;

        $decoratedElements = collect($elements)->map(function ($element, $i) {
            return $element->attr(['data-label' => $this->getHeader($i)]);
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