<?php

namespace Condoedge\Utils\Kompo\Elements;

use Condoedge\Utils\Services\LazyComponent\LazyComponentRegistry;
use Kompo\Rows;

class LazyComponent extends Rows
{
    protected $lazyId;
    protected $lazyHasTrigger = true;
    protected $closure; // Just to be able to execute directly if required

    /**
     * Create a lazy-loaded component from a pre-registered lazy ID.
     *
     * @param string        $lazyId      The HMAC-signed lazy component ID from the registry
     * @param string|object $placeholder  Preset name or custom placeholder element
     */
    public function __construct(string $lazyId, $placeholder = 'default', $closure = null)
    {
        $this->lazyId = $lazyId;
        $this->closure = $closure;

        $panelId = 'lazy-p-' . substr(md5($lazyId), 0, 10);
        $containerId = 'lazy-c-' . substr(md5($lazyId), 0, 10);

        $placeholderEl = is_string($placeholder)
            ? _lazyPlaceholder($placeholder)
            : $placeholder;

        $trigger = _Hidden()->onLoad
            ->post('_execute-lazy', null, ['_lazyId' => $lazyId])
            ->inPanel($panelId);

        parent::__construct($trigger, _Panel($placeholderEl)->id($panelId));

        $this->id($containerId)
             ->config([
                 '_lazyId' => $lazyId,
                 '_lazyPanelId' => $panelId,
                 '_lazyContainerId' => $containerId,
                 '_lazyHasTrigger' => true,
             ]);
    }

    /**
     * Group this lazy component into a batch for a single combined AJAX request.
     *
     * @param string $batchId  Shared batch identifier
     * @return self
     */
    public function batch(string $batchId)
    {
        $panelId = $this->config('_lazyPanelId');

        LazyComponentRegistry::addToBatch($batchId, $this->lazyId, $panelId);

        // Remove inline trigger — batch coordinator handles it
        if ($this->lazyHasTrigger) {
            array_shift($this->elements);
            $this->lazyHasTrigger = false;
            $this->config(['_lazyHasTrigger' => false]);
        }

        return $this;
    }
}
