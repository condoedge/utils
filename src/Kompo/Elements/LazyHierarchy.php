<?php

namespace Condoedge\Utils\Kompo\Elements;

use Condoedge\Utils\Services\LazyHierarchy\LazyHierarchyRegistry;
use Kompo\Elements\Block;
use Kompo\Routing\RouteFinder;

class LazyHierarchy extends Block
{
    public $vueComponent = 'LazyHierarchy';

    public function initialize($label = '')
    {
        parent::initialize($label);

        $this->config([
            'rootKey' => 'root',
            'parentParam' => 'parent_id',
            'cursorParam' => 'cursor',
            'params' => [],
            'limit' => 20,
            'lookahead' => 80,
            'autoLoad' => true,
            'deferLoad' => false,
            'loadingLabel' => __('Loading...'),
            'emptyLabel' => __('No items found'),
            'errorLabel' => __('Unable to load items'),
            'showMoreLabel' => __('Show more'),
            'displayMode' => 'inline',
            'classes' => [],
            'labels' => [],
        ]);
    }

    public function source(string $sourceClass, array $store = [])
    {
        $sourceId = app(LazyHierarchyRegistry::class)->storeSource($sourceClass, $store);

        return $this
            ->hierarchySource('utils.lazy-hierarchy.bootstrap', 'utils.lazy-hierarchy.nodes')
            ->hierarchyParam('_lazyHierarchyId', $sourceId)
            ->config([
                'sourceClass' => $sourceClass,
            ]);
    }

    public function hierarchySource(string $bootstrapRoute, ?string $nodesRoute = null, $bootstrapParameters = null, $nodesParameters = null)
    {
        return $this->hierarchyUrls(
            RouteFinder::guessRoute($bootstrapRoute, $bootstrapParameters),
            RouteFinder::guessRoute($nodesRoute ?: $bootstrapRoute, $nodesParameters),
        );
    }

    public function hierarchyUrls(string $bootstrapUrl, string $nodesUrl)
    {
        return $this->config([
            'bootstrapUrl' => $bootstrapUrl,
            'nodesUrl' => $nodesUrl,
        ]);
    }

    public function hierarchyParams(array $params)
    {
        return $this->config([
            'params' => array_replace($this->config('params') ?: [], $params),
        ]);
    }

    public function hierarchyParam(string $key, mixed $value)
    {
        return $this->hierarchyParams([$key => $value]);
    }

    public function hierarchyRequest(array $params = [])
    {
        return $this->hierarchyParams($params);
    }

    public function hierarchyPaging(int $limit = 20, int $lookahead = 80)
    {
        return $this->config([
            'limit' => $limit,
            'lookahead' => $lookahead,
        ]);
    }

    public function hierarchyPerPage(int $limit)
    {
        return $this->config([
            'limit' => $limit,
        ]);
    }

    public function hierarchyLookahead(int $lookahead)
    {
        return $this->config([
            'lookahead' => $lookahead,
        ]);
    }

    public function hierarchyParamNames(string $parentParam = 'parent_id', string $cursorParam = 'cursor')
    {
        return $this->config([
            'parentParam' => $parentParam,
            'cursorParam' => $cursorParam,
        ]);
    }

    public function hierarchyRootKey(string $rootKey)
    {
        return $this->config([
            'rootKey' => $rootKey,
        ]);
    }

    public function hierarchyIndent(float $base = 1, float $step = 0.9, int $maxDepth = 5)
    {
        return $this->config([
            'baseIndent' => $base,
            'indentStep' => $step,
            'maxIndentDepth' => $maxDepth,
        ]);
    }

    public function hierarchyLabels(array $labels)
    {
        return $this->config([
            'labels' => array_replace($this->config('labels') ?: [], $labels),
        ]);
    }

    public function hierarchyClasses(array $classes)
    {
        return $this->config([
            'classes' => array_replace($this->config('classes') ?: [], $classes),
        ]);
    }

    public function lazyLoad(bool $defer = true)
    {
        return $this->loadDeferred($defer);
    }

    public function loadDeferred(bool $defer = true)
    {
        return $this->config([
            'autoLoad' => true,
            'deferLoad' => $defer,
        ]);
    }

    public function loadImmediately()
    {
        return $this->loadDeferred(false);
    }

    public function manualLoad()
    {
        return $this->config([
            'autoLoad' => false,
        ]);
    }

    public function dropdown(?string $triggerLabel = null)
    {
        return $this->config(array_filter([
            'displayMode' => 'dropdown',
            'triggerLabel' => $triggerLabel,
            'deferLoad' => true,
        ], fn($value) => $value !== null));
    }

    public function inline()
    {
        return $this->config([
            'displayMode' => 'inline',
        ]);
    }

    public function searchable(?string $placeholder = null, string $param = 'search', int $debounce = 350)
    {
        return $this->config([
            'search' => [
                'enabled' => true,
                'placeholder' => $placeholder ?: __('Search'),
                'param' => $param,
                'debounce' => $debounce,
            ],
        ]);
    }

    public function modes(array $modes, string $param = 'mode', ?string $default = null)
    {
        return $this->config([
            'modes' => [
                'options' => $modes,
                'param' => $param,
                'default' => $default ?: array_key_first($modes),
            ],
        ]);
    }

    public function hierarchyAction(string $key, string $url, string $method = 'POST', array $config = [])
    {
        $actions = $this->config('actions') ?: [];

        $actions[$key] = array_replace([
            'url' => $url,
            'method' => $method,
        ], $config);

        return $this->config([
            'actions' => $actions,
        ]);
    }

    public function triggerLabel(?string $label)
    {
        return $this->config([
            'triggerLabel' => $label,
        ]);
    }
}
