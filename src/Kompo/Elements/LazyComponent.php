<?php

namespace Condoedge\Utils\Kompo\Elements;

use Closure;
use Condoedge\Utils\Services\LazyComponent\LazyComponentRef;
use Illuminate\Support\Facades\Crypt;
use Kompo\Rows;

class LazyComponent extends Rows
{
    protected $lazyRef;
    protected $closure; // Kept so _LazyTabs can run the active tab eagerly.

    /**
     * Create a lazy-loaded component from a stored closure.
     *
     * The trigger carries two separate things: the key of the shared compiled file,
     * and this render's captured variables, encrypted. Keeping them apart is the
     * point — the file is shared by every render of the call site, the variables
     * are not.
     *
     * @param LazyComponentRef $ref         Key + captured variables from the registry
     * @param string|object    $placeholder Preset name or custom placeholder element
     */
    public function __construct(LazyComponentRef $ref, $placeholder = 'default', ?Closure $closure = null)
    {
        $this->lazyRef = $ref;
        $this->closure = $closure;

        // Per-instance ids, not derived from the key: one call site rendered in a loop
        // shares a key, and would otherwise emit duplicate DOM ids for every row.
        $panelId = uniqid('lazy-p-');
        $containerId = uniqid('lazy-c-');

        $placeholderEl = is_string($placeholder)
            ? _lazyPlaceholder($placeholder)
            : $placeholder;

        $trigger = _Hidden()->onLoad
            ->post('_execute-lazy', null, static::lazyPayload($ref))
            ->inPanel($panelId);

        parent::__construct($trigger, _Panel($placeholderEl)->id($panelId)->style('min-height: var(--lazy-h, 300px);'));

        $this->id($containerId)
             ->config([
                 '_lazyId' => $ref->key,
                 '_lazyPanelId' => $panelId,
                 '_lazyContainerId' => $containerId,
             ]);
    }

    /**
     * Request params for a lazy fetch.
     *
     * Captured variables are encrypted with the same authenticated encryption Kompo
     * already uses for kompoInfo, so the client can neither read nor tamper with
     * them. Encryption is not authorization: the endpoint is behind `auth` and the
     * target komponent still runs its own checks.
     */
    public static function lazyPayload(LazyComponentRef $ref): array
    {
        $payload = ['_lazyId' => $ref->key];

        if ($ref->use !== []) {
            $payload['_lazyUse'] = Crypt::encrypt($ref->use);
        }

        return $payload;
    }

    /** The captured variables for this render, for callers that execute the closure inline. */
    public function lazyUse(): array
    {
        return $this->lazyRef->use;
    }
}
