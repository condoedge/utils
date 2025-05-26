<?php

namespace Condoedge\Utils\Kompo\Elements;

use Kompo\Core\IconGenerator;
use Kompo\Elements\Element;
use Kompo\Rows;

class Collapsible extends Rows
{
    public $vueComponent = 'Collapsible';

    protected $titleElClass;

    public function initialize($label = '')
    {
        parent::initialize($label);

        $this->titleElClass('font-semibold');

        $this->class('gap-3 flex flex-col');

        $this->withIcon('chevron-up');
    }

    public function mounted()
    {
        parent::mounted();

        if ($this->config('expandBasedInLinks')) {
            $this->config([
                'links' => $this->getLinksFromElements(cascade: true)->implode(','),
            ]);
        }
    }

    public function titleLabel(string|Element $titleLabel)
    {
        return $this->config([
            'titleEl' => $this->titleEl($titleLabel),
        ]);
    }

    public function titleElClass(string $titleElClass)
    {
        $this->titleElClass = $titleElClass;

        return $this->config([
            'titleElClass' => $titleElClass,
        ]);
    }

    public function collapsedClass(string $collapsedClass)
    {
        return $this->config(['collapsedClass' => $collapsedClass]);
    }

    public function expandedClass(string $expandedClass)
    {
        return $this->config([
            'expandedClass' => $expandedClass . ' VlExpanded',
        ]);
    }

    public function wrapperElementsClass(string $wrapperElementClass)
    {
        return $this->config([
            'wrapperElementsClass' => $wrapperElementClass,
        ]);
    }

    public function expandBasedInLinks()
    {
        return $this->config([
            'expandBasedInLinks' => 1,
        ]);
    }

    public function withIcon(string $icon, string $classes = '') {
        return $this->config([
            'iconT' => IconGenerator::toHtml($icon, $classes),
        ]);
    }

    protected function getLinksFromElements($el = null, $cascade = true)
    {
        $el = $el ?: $this;

        $links = collect();

        foreach ($el->elements as $item) {
            $validLink = isset($item->href) && $item->href != 'javascript:void(0)';

            if (!$validLink && isset($item->elements) && $cascade) {

                foreach ($item->elements as $subItem) {
                    $links = $links->merge($this->getLinksFromElements($subItem, $cascade));
                }

                continue;
            }

            if ($validLink) $links->push($item->href ?? null);
        }

        return $links->filter();
    }

    protected function titleEl($titleLabel)
    {
        return $titleLabel instanceof Element ? $titleLabel : _Flex(
            _Html($titleLabel)->class($this->titleElClass),
        )->class('py-2 w-full');
    }
}
