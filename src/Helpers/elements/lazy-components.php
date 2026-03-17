<?php

use Condoedge\Utils\Services\LazyComponent\LazyComponentRegistry;

if (!function_exists('lazySpinnerPlaceholder')) {
    function lazySpinnerPlaceholder($minHeight = '200px')
    {
        return _Rows(_Rows(
            _Rows()->class('bg-gray-200 opacity-50 w-full rounded-xl')->style('min-height: ' . $minHeight . ';'),
        )->class('vlPanelLoading w-full rounded-xl overflow-hidden'))->class('p-8');
    }
}

\Kompo\Elements\BaseElement::macro('refreshLazyComponent', function ($params = [], $componentName = null) {
    $componentName = $componentName ?? request('componentFn');

    return $this->selfGet($componentName, array_merge(request()->all(), $params))->inPanel(request('panel_id'));
});

if (!function_exists('lazyComponent')) {
    function lazyComponent($componentFnName, $placeholder = null, $params = [])
    {
        $panelId = uniqid('lazy-component-');
        $containerId = uniqid('lazy-component-container-');

        $placeholder = $placeholder ?: lazySpinnerPlaceholder();

        $params = array_merge($params, ['_lazy' => true, 'panel_id' => $panelId, 'container_id' => $containerId, 'componentFn' => $componentFnName]);

        return _Rows(
            _Hidden()->onLoad->selfGet($componentFnName, $params)->inPanel($panelId)->panelLoading($containerId),

            _Panel(
                $placeholder
            )->id($panelId),
        )->id($containerId);
    }
}

if (!function_exists('lazyBatchComponent')) {
    function lazyBatchComponent($componentFnName, $placeholder = null, $placeholderSizes = 'w-6 h-4')
    {
        if ($placeholder) {
            return _Div($placeholder->id($componentFnName));
        }

        return _Div(
            _Rows()->class('bg-white animate-pulse rounded-lg opacity-40 animation-duration-100')->class($placeholderSizes)
                ->id($componentFnName)
        );
    }
}

if (!function_exists('_LazyComponent')) {
    /**
     * Create a lazy-loaded component using an inline closure or Komponent class.
     *
     * Closures are compiled to storage/framework/kompo-lazy/ with deterministic keys.
     * In dev, recompiles when source file changes. In prod, compiled once.
     *
     * Usage:
     *   _LazyComponent(fn() => _Html('content'), 'card')
     *   _LazyComponent(fn() => $this->heavyMethod(), 'metric')->batch('dashboard')
     *   _LazyComponent(MyForm::class, ['campaign_id' => $id], 'card')
     *
     * @param Closure|string $fn         Closure returning elements, or Komponent class name
     * @param string|array|object $placeholder  Preset name, custom element, or store array (when $fn is class)
     * @param string|object $presetOrPlaceholder  Preset name when using class syntax
     * @return \Condoedge\Utils\Kompo\Elements\LazyComponent
     */
    function _LazyComponent($fn, $placeholder = 'default', $presetOrPlaceholder = null)
    {
        $registry = new LazyComponentRegistry();

        // Sugar syntax: _LazyComponent(MyForm::class, ['store' => 'data'], 'card')
        if (is_string($fn) && !($fn instanceof \Closure) && class_exists($fn)) {
            $store = is_array($placeholder) ? $placeholder : [];
            $placeholder = $presetOrPlaceholder ?? 'default';
            $lazyId = $registry->storeKomponentClass($fn, $store);
        } else {
            $lazyId = $registry->store($fn);
        }

        return new \Condoedge\Utils\Kompo\Elements\LazyComponent($lazyId, $placeholder, $fn instanceof \Closure ? $fn : null);
    }
}

if (!function_exists('_lazyPlaceholder')) {
    /**
     * Returns a skeleton placeholder element for the given preset.
     *
     * CSS variables (set via ->style() on the _LazyComponent container):
     *   --lazy-bg        Background color of shimmer bars (default: #e5e7eb / gray-200)
     *   --lazy-opacity   Opacity of secondary bars (default: varies by preset)
     *   --lazy-h         Height of the main skeleton block (default: varies by preset)
     *   --lazy-w         Width of the main skeleton block (default: 100%)
     *
     * Presets:
     *   'default' — Clean skeleton pulse (gray shimmer, no spinner)
     *   'spinner' — Legacy spinner overlay (vlPanelLoading)
     *   'metric'  — Small card matching _BoxLabelNum dimensions
     *   'number'  — Compact inline number placeholder
     *   'chart'   — Tall card matching ApexChart dimensions
     *   'card'    — Standard card-sized skeleton
     *   'table'   — Multiple rows matching table layout
     *   'section' — Full-width section skeleton
     */
    function _lazyPlaceholder(string $preset = 'default')
    {
        $bar = 'animate-pulse rounded-lg';
        $bg = 'background-color: var(--lazy-bg, #e5e7eb)';
        $bgDim = 'background-color: var(--lazy-bg, #e5e7eb); opacity: var(--lazy-opacity, 0.5)';
        $bgFaint = 'background-color: var(--lazy-bg, #e5e7eb); opacity: var(--lazy-opacity, 0.3)';

        return match($preset) {
            'default' => _Rows(
                _Rows()->class("$bar rounded-xl")
                    ->style("$bgDim; min-height: var(--lazy-h, 200px); width: var(--lazy-w, 100%)"),
            )->class('animate-pulse'),

            'spinner' => lazySpinnerPlaceholder(),

            'metric' => _Rows(
                _Rows()->class("$bar h-3 w-24 mb-2")->style($bg),
                _Rows()->class("$bar h-7 w-16")->style($bg),
            )->class('animate-pulse')->style('min-height: var(--lazy-h, auto); width: var(--lazy-w, auto)'),

            'number' => _Rows(
                _Rows()->class("$bar h-6 w-16")->style($bg),
            )->class('animate-pulse')->style('min-height: var(--lazy-h, auto); width: var(--lazy-w, auto)'),

            'chart' => _Rows(
                _Rows()->class("$bar h-4 w-32 mb-4")->style($bg),
                _Rows()->class("$bar rounded-xl")
                    ->style("$bgDim; min-height: var(--lazy-h, 300px); width: var(--lazy-w, 100%)"),
            )->class('animate-pulse'),

            'card' => _Rows(
                _Rows()->class("$bar h-4 w-40 mb-3")->style($bg),
                _Rows()->class("$bar h-3 w-full mb-2")->style($bgDim),
                _Rows()->class("$bar h-3 w-3/4")->style($bgDim),
            )->class('animate-pulse')->style('min-height: var(--lazy-h, auto); width: var(--lazy-w, auto)'),

            'table' => _Rows(
                _Rows()->class("$bar h-4 w-full mb-4")->style($bg),
                ...array_map(fn() =>
                    _Rows()->class("$bar h-3 w-full mb-3")->style($bgDim),
                    range(1, 5)
                ),
            )->class('animate-pulse')->style('min-height: var(--lazy-h, auto); width: var(--lazy-w, auto)'),

            'section' => _Rows(
                _Rows()->class("$bar h-5 w-48 mb-4")->style($bg),
                _Columns(
                    _Rows()->class("$bar rounded-xl")->style("$bgFaint; min-height: var(--lazy-h, 200px)")->col('col-md-6'),
                    _Rows()->class("$bar rounded-xl")->style("$bgFaint; min-height: var(--lazy-h, 200px)")->col('col-md-6'),
                ),
            )->class('animate-pulse')->style('width: var(--lazy-w, auto)'),

            default => _Rows(
                _Rows()->class("$bar rounded-xl")
                    ->style("$bgDim; min-height: var(--lazy-h, 200px); width: var(--lazy-w, 100%)"),
            )->class('animate-pulse'),
        };
    }
}

function _LazyTabs(...$tabs)
{
    $currentTab = request('tab_number', 0);

    return _ResponsiveTabs(
        ...collect($tabs)->map(function ($tab, $index) use ($currentTab) {
            if ($index == $currentTab) {
                $lazyElement = getPrivateProperty($tab, 'elements')[0] ?? null;
                $closure = getPrivateProperty($lazyElement, 'closure') ?? null;

                return _SwipeableTab(
                    $closure(),
                )->label(getPrivateProperty($tab, 'label'));
            }

            return $tab;
        })->all(),
    );
}

if (!function_exists('_LazyTab')) {
    function _LazyTab($closure, $placeholderPreset = 'default')
    {
        return _SwipeableTab(
            _LazyComponent($closure, $placeholderPreset),
        )->style('--lazy-bg: #fff; --lazy-opacity: 0.65; --lazy-h: 300px');
    }
}

if (!function_exists('_Tab')) {
    function _Tab()
    {
        return _SwipeableTab(...func_get_args());
    }
}
