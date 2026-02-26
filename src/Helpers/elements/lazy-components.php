<?php

if (!function_exists('lazySpinnerPlaceholder')) {
    function lazySpinnerPlaceholder()
    {
        return _Rows(_Rows(
            _Rows()->class('bg-gray-200 opacity-50 w-full rounded-xl')->style('min-height:200px'),
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