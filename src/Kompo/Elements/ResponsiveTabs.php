<?php

namespace Condoedge\Utils\Kompo\Elements;

use Kompo\Rows;

class ResponsiveTabs extends Rows
{
    public $vueComponent = 'Rows';

    protected $tabLabels;
    protected $uniqueId;

    protected $breakpoint;

    protected $tabsClass;
    protected $tabsSelectedClass;
    protected $tabsUnselectedClass;
    protected $tabsCommonClass;

    protected $tabsCallbackDecoration;

    protected $tabParamKey = 'tab_number';

    protected $activeTab;
    protected $eagerActiveTab = false;

    public static $breakpoints = [
        'sm' => 'sm:block sm:hidden',
        'md' => 'md:block md:hidden',
        'lg' => 'lg:block lg:hidden',
        'xl' => 'xl:block xl:hidden',
        '2xl' => '2xl:block 2xl:hidden',
    ];

    public function __construct(...$args)
    {
        parent::__construct(...$args);

        $this->breakpoint('md');

        $this->tabLabels = collect($this->elements)->map(fn ($tab) => $tab?->label)->filter();
        $this->uniqueId = uniqid();
    }

    // Kompo boots the children here, before mounted(): the active lazy tab has to be in place by then.
    public function prepareForDisplay($komponent)
    {
        if ($this->eagerActiveTab) {
            $this->renderLazyTab($this->activeTabIndex());
        }

        parent::prepareForDisplay($komponent);
    }

    public function mounted()
    {
        $activeTab = $this->activeTabIndex();

        $this->elements = [
            $this->selectTabs($activeTab),
            $this->tabsDecorated($activeTab),
        ];
    }

    /**
     * The tab shown first: its index (from 0) or the id given to the tab with ->id().
     * A numeric tab parameter already in the URL still wins, as it does in the browser.
     */
    public function activeTab($indexOrId = null)
    {
        $this->activeTab = $indexOrId;

        return $this;
    }

    /** Renders the active tab with the page instead of lazily, when it holds a _LazyComponent. */
    public function eagerActiveTab($eager = true)
    {
        $this->eagerActiveTab = $eager;

        return $this;
    }

    protected function activeTabIndex()
    {
        $persisted = $this->tabParamKey ? request($this->tabParamKey) : null;

        $wanted = is_numeric($persisted) ? $persisted : ($this->activeTab ?? $persisted);

        if (is_numeric($wanted)) {
            return (int) $wanted;
        }

        $index = $wanted ? collect($this->elements)->search(fn ($tab) => $tab?->id == $wanted) : false;

        return $index === false ? 0 : $index;
    }

    protected function renderLazyTab($index)
    {
        $tab = $this->elements[$index] ?? null;
        $lazyElement = $tab?->elements[0] ?? null;
        $closure = is_object($lazyElement) ? getPrivateProperty($lazyElement, 'closure') : null;

        if ($closure) {
            $tab->content($closure());
        }
    }

    public function breakpoint(string $breakpoint)
    {
        $this->breakpoint = $breakpoint;

        return $this;
    }

    public function tabsClass(string|null $tabsClass, $override = false)
    {
        if ($override) {
            $this->tabsClass = $tabsClass;
        } else {
            $this->tabsClass = $this->tabsClass ? $this->tabsClass . ' ' . $tabsClass : $tabsClass;
        }

        return $this;
    }

    public function tabsSelectedClass(string|null $tabsSelectedClass, $override = false)
    {
        if ($override) {
            $this->tabsSelectedClass = $tabsSelectedClass;
        } else {
            $this->tabsSelectedClass = $this->tabsSelectedClass ? $this->tabsSelectedClass . ' ' . $tabsSelectedClass : $tabsSelectedClass;
        }

        return $this;
    }

    public function tabsUnselectedClass(string|null $tabsUnselectedClass, $override = false)
    {
        if ($override) {
            $this->tabsUnselectedClass = $tabsUnselectedClass;
        } else {
            $this->tabsUnselectedClass = $this->tabsUnselectedClass ? $this->tabsUnselectedClass . ' ' . $tabsUnselectedClass : $tabsUnselectedClass;
        }

        return $this;
    }

    public function tabsCommonClass(string|null $tabsCommonClass, $override = false)
    {
        if ($override) {
            $this->tabsCommonClass = $tabsCommonClass;
        } else {
            $this->tabsCommonClass = $this->tabsCommonClass ? $this->tabsCommonClass . ' ' . $tabsCommonClass : $tabsCommonClass;
        }

        return $this;
    }

    public function tabsCallbackDecoration($tabsCallbackDecoration)
    {
        $this->tabsCallbackDecoration = $tabsCallbackDecoration;

        return $this;
    }

    public function tabParamKey($key = 'tab_number')
    {
        $this->tabParamKey = $key;

        return $this;
    }

    protected function tabsDecorated($activeTab)
    {
        $callback = $this->tabsCallbackDecoration;

        return _SwipeableTabs(...$this->elements)
            ->activeTab($activeTab)
            ->commonClass("hidden {$this->breakpoint}:block mr-8")
            ->when($this->tabsClass, fn ($el) => $el->class($this->tabsClass))
            ->when($this->tabsCommonClass, fn ($el) => $el->commonClass($this->tabsCommonClass . " hidden {$this->breakpoint}:block"))
            ->when($this->tabsSelectedClass, fn ($el) => $el->selectedClass($this->tabsSelectedClass, $this->tabsUnselectedClass))
            ->id('responsive-tabs-' . $this->uniqueId)
            ->config(['selectId' => 'tabs-select-' . $this->uniqueId])
            ->config(['tabParamKey' => $this->tabParamKey])
            ->when($callback && is_callable($callback), fn ($el) => $callback($el));
    }

    protected function selectTabs($activeTab)
    {
        return _Select()
            ->placeholder($this->tabLabels[0])
            ->id('tabs-select-' . $this->uniqueId)
            ->name('tabs_select', false)
            ->class("block {$this->breakpoint}:hidden without-x-icon whiteField")
            ->attr([
                'readonly' => 'readonly',
            ])
            ->options($this->tabLabels)
            ->value($activeTab)
            ->onChange(
                fn ($e) => $e
                    ->run($this->jsSelect())
            );
    }

    protected function jsSelect()
    {
        return '() => {
            function activateTabFromSelect(id)
            {
                let tabOptions = $(`#tabs-select-${id}`).closest(".vlInputWrapper")[0].querySelectorAll(".vlOption");
                let tabIndex = [...tabOptions].findIndex(option => option.classList.contains("vlSelected"));

                if (tabIndex == -1) {
                    tabOptions[0].click();
                    return 0;
                }

                let tabs = document.querySelectorAll(`#responsive-tabs-${id} a[role=tab]`);

                [...tabs][tabIndex >= 0 ? tabIndex : 0].click();

                return tabIndex;
            }

            let tabIndex = activateTabFromSelect("' . $this->uniqueId . '");

            (
                ' . getPushParameterFn($this->tabParamKey ?: 'tab_number', 'tabIndex', true) . '
            )()
        }';
    }
}
