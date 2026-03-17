<?php

namespace Condoedge\Utils\Http\Controllers;

use Closure;
use Condoedge\Utils\Services\LazyComponent\LazyComponentRegistry;
use Illuminate\Http\Request;
use Kompo\Elements\BaseElement;
use Kompo\Routing\Dispatcher;

class LazyComponentController
{
    public function execute(Request $request)
    {
        $lazyId = $request->input('_lazyId');

        if (!$lazyId) {
            abort(400, 'Missing lazy component ID.');
        }

        $registry = new LazyComponentRegistry();
        $retrieved = $registry->retrieve($lazyId);

        if (!$retrieved) {
            abort(404, 'Lazy component expired or not found.');
        }

        // Komponent class reference (sugar syntax)
        if (is_array($retrieved) && ($retrieved['_type'] ?? null) === 'komponent') {
            $komponentClass = $retrieved['class'];
            return with(new Dispatcher($komponentClass))->bootKomponentForDisplay();
        }

        // Closure — boot Komponent from KompoInfo to provide $this context
        $komponent = Dispatcher::bootKomponentForAction();

        $bound = Closure::bind($retrieved, $komponent, get_class($komponent));
        $result = $bound ? $bound() : $retrieved();

        return static::prepareElementsResult($result, $komponent);
    }

    public function executeBatch(Request $request)
    {
        $lazyItemsJson = $request->input('_lazyItems');

        if (!$lazyItemsJson) {
            abort(400, 'Missing lazy batch items.');
        }

        $lazyItems = json_decode($lazyItemsJson, true);

        // Boot Komponent once for all closures
        $komponent = Dispatcher::bootKomponentForAction();

        $registry = new LazyComponentRegistry();
        $results = [];

        foreach ($lazyItems as $item) {
            $retrieved = $registry->retrieve($item['lazyId']);

            if (!$retrieved) {
                $results[$item['panelId']] = null;
                continue;
            }

            // Komponent class reference
            if (is_array($retrieved) && ($retrieved['_type'] ?? null) === 'komponent') {
                $komponentClass = $retrieved['class'];
                $results[$item['panelId']] = with(new Dispatcher($komponentClass))->bootKomponentForDisplay();
                continue;
            }

            // Closure
            $bound = Closure::bind($retrieved, $komponent, get_class($komponent));
            $result = $bound ? $bound() : $retrieved();

            $results[$item['panelId']] = static::prepareElementsResult($result, $komponent);
        }

        return $results;
    }

    protected static function prepareElementsResult($result, $komponent)
    {
        if (is_array($result)) {
            return collect($result)->filter()->each(function ($el) use ($komponent) {
                if ($el instanceof BaseElement) {
                    $el->prepareForDisplay($komponent);
                    $el->mountedHook($komponent);
                }
            })->values()->all();
        }

        return $result;
    }
}
