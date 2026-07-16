<?php

namespace Condoedge\Utils\Http\Controllers;

use Closure;
use Condoedge\Utils\Services\LazyComponent\LazyComponentRegistry;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
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

        $use = $this->decryptUse($request->input('_lazyUse'));

        $closure = (new LazyComponentRegistry())->retrieve($lazyId, $use);

        if (!$closure) {
            abort(404, 'Lazy component expired or not found.');
        }

        // Boot the host Komponent to provide $this. Its own state comes from the
        // encrypted kompoInfo, so $this is this request's — never a stale snapshot.
        $komponent = Dispatcher::bootKomponentForAction();

        app()->instance('bootFlag', true);

        $bound = Closure::bind($closure, $komponent, get_class($komponent));
        $result = $bound ? $bound() : $closure();

        return static::prepareElementsResult($result, $komponent);
    }

    /**
     * The captured variables are authenticated-encrypted, so a tampered payload fails
     * closed rather than executing the closure with attacker-chosen values.
     */
    protected function decryptUse($encrypted): array
    {
        if (!$encrypted) {
            return [];
        }

        try {
            $use = Crypt::decrypt($encrypted);
        } catch (DecryptException) {
            abort(400, 'Invalid lazy component payload.');
        }

        return is_array($use) ? $use : [];
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
