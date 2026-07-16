<?php

namespace Condoedge\Utils\Kompo\Elements;

use Condoedge\Utils\Services\LazyComponent\LazyComponentRegistry;
use Kompo\Rows;

/**
 * A lazy-loading collapsible: title bar always visible; body slides in/out
 * on click. Body content lazy-loads via a closure on FIRST expand only;
 * subsequent toggles are pure CSS slide animations (no re-fetch).
 *
 * Internally:
 *   - The closure is registered with `LazyComponentRegistry` and executed by
 *     the existing `_execute-lazy` endpoint (the same path `_LazyComponent`
 *     uses). No `selfMethods` whitelist needed on the host Komponent.
 *   - The slide animation uses jQuery `slideToggle` on a block-display
 *     wrapper. The skeleton placeholder is shown inside the wrapper while
 *     the AJAX is in flight; the response replaces it.
 *   - A hidden trigger element holds the kompo `post(...).inPanel(...)`
 *     chain and is clicked programmatically by the title's JS handler on
 *     first expand. Re-using kompo's AJAX path means CSRF, kompoInfo
 *     headers, and response wiring all "just work".
 *
 * Usage:
 *   _LazyCollapsible(
 *       _Flex(_Html('Section name'), _Html()->icon('icon-up')->id('my-icon')),
 *       fn() => new MyHeavyTable(['id' => $section->id]),
 *       'table',                                 // skeleton preset
 *       iconId: 'my-icon',                       // optional: rotates on toggle
 *   )
 */
class LazyCollapsible extends Rows
{
    public function __construct($title, $bodyClosure, string $skeletonPreset = 'default', ?string $iconId = null)
    {
        $ref = (new LazyComponentRegistry())->store($bodyClosure);

        // Per-instance ids: the key is now shared by every render of the call site, so
        // deriving ids from it would collide when a collapsible is rendered in a loop.
        $shortId = uniqid();
        $bodyPanelId = 'lazy-collapsible-body-' . $shortId;
        $triggerId   = 'lazy-collapsible-trigger-' . $shortId;
        $wrapperClass = 'lazy-collapsible-' . $shortId;

        $skeleton = is_string($skeletonPreset) ? _lazyPlaceholder($skeletonPreset) : $skeletonPreset;

        // Hidden trigger: its own onClick fires the lazy-load AJAX through
        // kompo's standard post(...).inPanel(...) pipeline. We .click() it
        // from JS on first expand so we get CSRF + kompoInfo headers for free.
        $trigger = _Link()
            ->id($triggerId)
            ->class('hidden')
            ->onClick(fn($e) => $e->post('_execute-lazy', null, LazyComponent::lazyPayload($ref))
                ->inPanel($bodyPanelId));

        // Title click: slide-toggle the wrapper, then trigger the AJAX once.
        $iconFlipJs = $iconId ? 'if (typeof toggleIcon === "function") toggleIcon("#' . $iconId . '");' : '';
        $titleClickJs = <<<JS
            () => {
                const \$wrapper = $(".{$wrapperClass}");
                \$wrapper.slideToggle();
                {$iconFlipJs}
                if (!\$wrapper.is(":visible")) return;
                if (\$wrapper.data("loaded")) return;
                \$wrapper.data("loaded", true);
                const trig = document.getElementById("{$triggerId}");
                if (trig) trig.click();
            }
        JS;

        $titleEl = $title
            ->class('cursor-pointer')
            ->onClick(fn($e) => $e->run($titleClickJs));

        // Wrapper uses _Div (block) so jQuery slideToggle animates cleanly
        // (flex containers break the animation). Initially hidden via the
        // Tailwind `hidden` class; jQuery flips inline display from there.
        $bodyWrapper = _Div(
            $trigger,
            _Panel($skeleton)->id($bodyPanelId),
        )
            ->class($wrapperClass)
            ->class('hidden');

        parent::__construct($titleEl, $bodyWrapper);
    }
}
