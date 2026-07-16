<?php

namespace Condoedge\Utils\Kompo\Elements;

use Closure;
use Condoedge\Utils\Services\LazyComponent\LazyComponentRef;
use Condoedge\Utils\Services\LazyComponent\LazyComponentRegistry;
use Kompo\Rows;

/**
 * Renders the side matching the current viewport EAGERLY (no skeleton, in the
 * initial HTML), and lazy-loads the OTHER side once — the first time the user
 * crosses the breakpoint into its range. The server can't read the viewport, so
 * the eager side is chosen from the request User-Agent (phone => mobile eager,
 * else desktop eager). A wrong guess is self-correcting: if the actual viewport
 * is in the lazy side's range, the JS gate loads it immediately on mount.
 *
 * The lazy side reuses the same mechanism as LazyCollapsible: its closure is
 * registered with LazyComponentRegistry and executed by the existing
 * `_execute-lazy` endpoint, so CSRF, kompoInfo headers and response wiring all
 * "just work". Only the lazy side is registered: registry keys come from each
 * closure's own file:line, so they no longer depend on how many closures were
 * registered before them in the request.
 *
 * Usage:
 *   _LazyResponsive(
 *       fn () => new MyInvoicesMobile(),   // shown below the breakpoint
 *       fn () => new MyInvoicesTable(),    // shown at/above the breakpoint
 *       'md',                              // 768px — matches Tailwind `md`
 *   )
 *
 * Pass closures (not pre-built elements) so the lazy side isn't built until it
 * actually loads.
 */
class LazyResponsive extends Rows
{
    /** Tailwind breakpoint name => min-width px (Tailwind defaults). */
    public static $minWidths = [
        'sm' => 640, 'md' => 768, 'lg' => 1024, 'xl' => 1280, '2xl' => 1536,
    ];

    public function __construct(
        Closure $mobile,
        Closure $desktop,
        string $breakpoint = 'md',
        string $mobilePreset = 'spinner',
        string $desktopPreset = 'table',
    ) {
        $minWidth = static::$minWidths[$breakpoint] ?? static::$minWidths['md'];

        $mobileDisplay  = "block {$breakpoint}:hidden";   // visible below the breakpoint
        $desktopDisplay = "hidden {$breakpoint}:block";   // visible at/above the breakpoint

        // Only the lazy side is registered — the eager side is executed inline, so
        // compiling it would write a file nobody loads.
        $registry = new LazyComponentRegistry();

        if ($this->isMobileRequest()) {
            // Mobile eager, desktop lazy.
            $eager = _Rows($mobile())->class($mobileDisplay);
            [$panel, $trigger, $trigId] = $this->lazySide($registry->store($desktop), $desktopPreset, $desktopDisplay);
            $lazyIsDesktop = true;
        } else {
            // Desktop eager, mobile lazy.
            $eager = _Rows($desktop())->class($desktopDisplay);
            [$panel, $trigger, $trigId] = $this->lazySide($registry->store($mobile), $mobilePreset, $mobileDisplay);
            $lazyIsDesktop = false;
        }

        $gate = _Hidden()->onLoad(fn ($e) => $e->run(
            $this->gateJs($minWidth, $trigId, $lazyIsDesktop)
        ));

        parent::__construct($gate, $eager, $trigger, $panel);
    }

    /** @return array{0:\Kompo\Panel,1:\Kompo\Link,2:string} [panel, trigger, triggerId] */
    protected function lazySide(LazyComponentRef $ref, string $preset, string $displayClass): array
    {
        // Per-instance ids: the key is shared across renders of the same call site.
        $shortId = uniqid();
        $panelId = 'lazy-responsive-panel-' . $shortId;
        $trigId  = 'lazy-responsive-trigger-' . $shortId;

        // Hidden trigger fires the lazy-load AJAX through kompo's standard
        // post(...).inPanel(...) pipeline. It's a sibling of the panel (not inside
        // it) so it survives the panel swap and the .click() guard holds.
        $trigger = _Link()
            ->id($trigId)
            ->class('hidden')
            ->onClick(fn ($e) => $e->post('_execute-lazy', null, LazyComponent::lazyPayload($ref))
                ->inPanel($panelId));

        $panel = _Panel(_lazyPlaceholder($preset))
            ->id($panelId)
            ->class($displayClass);

        return [$panel, $trigger, $trigId];
    }

    /**
     * Gate: load the lazy side only when the current viewport is in ITS range —
     * on mount (covers a wrong UA guess / first load already on that side) and on
     * the first resize across the breakpoint. Loads at most once, then detaches.
     */
    protected function gateJs(int $minWidth, string $lazyTrigId, bool $lazyIsDesktop): string
    {
        $lazyIsDesktopJs = $lazyIsDesktop ? 'true' : 'false';

        return <<<JS
            () => {
                const mq = window.matchMedia('(min-width: {$minWidth}px)');  // true = desktop range
                const lazyIsDesktop = {$lazyIsDesktopJs};
                const fire = () => {
                    if (mq.matches !== lazyIsDesktop) return;   // current viewport not in the lazy side's range
                    const t = document.getElementById('{$lazyTrigId}');
                    if (t && !t.dataset.lazyFired) { t.dataset.lazyFired = '1'; t.click(); }
                };
                const onChange = () => {
                    fire();
                    const t = document.getElementById('{$lazyTrigId}');
                    if (t && t.dataset.lazyFired) mq.removeEventListener('change', onChange);
                };
                mq.addEventListener('change', onChange);
                setTimeout(fire, 0);
            }
        JS;
    }

    /** Phone heuristic from the request User-Agent (tablets/desktops => false). */
    protected function isMobileRequest(): bool
    {
        $ua = (string) request()->header('User-Agent');

        return $ua !== '' && preg_match('/Mobi|Android|iPhone|iPod|webOS|BlackBerry|IEMobile|Opera Mini/i', $ua) === 1;
    }
}
