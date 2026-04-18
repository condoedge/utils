<?php

namespace Condoedge\Utils\Http\Controllers;

use Condoedge\Utils\Contracts\LazyHierarchySourceInterface;
use Condoedge\Utils\Services\LazyHierarchy\LazyHierarchyRegistry;
use Illuminate\Http\Request;
use Kompo\Elements\BaseElement;
use Kompo\Routing\Dispatcher;
use Throwable;

class LazyHierarchyController
{
    public function bootstrap(Request $request)
    {
        return response()->json($this->preparePayload(
            $this->source($request)->bootstrap($request),
        ));
    }

    public function children(Request $request)
    {
        return response()->json($this->preparePayload(
            $this->source($request)->children($request),
        ));
    }

    protected function source(Request $request): LazyHierarchySourceInterface
    {
        $sourceId = $request->query('_lazyHierarchyId') ?: $request->input('_lazyHierarchyId');

        if (!$sourceId) {
            abort(400, 'Missing lazy hierarchy source.');
        }

        $sourceData = app(LazyHierarchyRegistry::class)->retrieveSource($sourceId);

        if (!$sourceData || !class_exists($sourceData['class'])) {
            abort(404, 'Lazy hierarchy source not found.');
        }

        $source = app()->makeWith($sourceData['class'], [
            'store' => $sourceData['store'] ?? [],
        ]);

        if (!$source instanceof LazyHierarchySourceInterface) {
            abort(500, 'Lazy hierarchy source must implement LazyHierarchySourceInterface.');
        }

        return $source;
    }

    protected function preparePayload(array $payload): array
    {
        if (!$this->payloadHasRenderables($payload)) {
            return $payload;
        }

        $komponent = $this->bootKomponentForRenderPreparation();

        foreach (($payload['nodes'] ?? []) as $nodeId => $node) {
            if (!array_key_exists('render', $node)) {
                continue;
            }

            $payload['nodes'][$nodeId]['render'] = $this->prepareRenderable($node['render'], $komponent);
        }

        return $payload;
    }

    protected function payloadHasRenderables(array $payload): bool
    {
        foreach (($payload['nodes'] ?? []) as $node) {
            if (array_key_exists('render', $node)) {
                return true;
            }
        }

        return false;
    }

    protected function bootKomponentForRenderPreparation()
    {
        try {
            app()->instance('bootFlag', true);

            return Dispatcher::bootKomponentForAction();
        } catch (Throwable) {
            return null;
        }
    }

    protected function prepareRenderable($renderable, $komponent)
    {
        if ($renderable instanceof BaseElement) {
            if ($komponent) {
                $renderable->prepareForDisplay($komponent);
                $renderable->mountedHook($komponent);
            }

            return $renderable;
        }

        if (is_array($renderable)) {
            return collect($renderable)
                ->map(fn($child) => $this->prepareRenderable($child, $komponent))
                ->all();
        }

        return $renderable;
    }
}
