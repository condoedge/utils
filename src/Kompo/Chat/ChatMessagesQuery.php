<?php

namespace Condoedge\Utils\Kompo\Chat;

use Condoedge\Utils\Kompo\Common\Query;

/**
 * ChatMessagesQuery - base Query for a chat message list.
 *
 * Carries the chat defaults that make Kompo's built-in chat layout kick in: with
 * paginationType 'Scroll' + topPagination + the default Horizontal layout the framework
 * adds flex-col-reverse to the INNER items layout, jumps to the bottom at mount, snaps
 * back after browse/refresh and loads older pages at scrollTop == 0. Never put
 * column-reverse on the scroll wrapper (.vlQueryWrapper) itself - scrollTop semantics
 * invert and pagination breaks.
 *
 * Consumers implement query() (newest first: ->orderByDesc('created_at')) and
 * render($item), give the komponent an id, and typically return
 * $this->snapToBottomOnLoad() from bottom() alongside their composer form.
 */
abstract class ChatMessagesQuery extends Query
{
    public $paginationType = 'Scroll';
    public $topPagination = true;
    public $bottomPagination = false;

    public $perPage = 30;

    public $noItemsFound = '';

    /**
     * The inner-items recipe: the framework already applies flex-col-reverse on the items
     * layout for Scroll+topPagination, but the explicit [&>div] variants keep the gap and
     * direction deterministic (same recipe the ai chat ships). The wrapper itself stays a
     * plain scrollable column ('overflow-y-auto ... flex-1 min-h-0').
     */
    public $itemsWrapperClass = '[&>div]:gap-4 [&>div]:flex [&>div]:flex-col-reverse p-6 overflow-y-auto mini-scroll flex-1 min-h-0';

    /**
     * The query root must be a flex column for the items wrapper's flex-1/min-h-0 sizing
     * to constrain the scroll area between top() and bottom().
     */
    public $style = 'display: flex; flex-direction: column;';

    /**
     * The snap-to-bottom carrier for bottom(). Elements inside bottom() remount and
     * re-fire their onLoad on every ->refresh() of this Query - which is exactly when
     * the list must snap back to the newest message (the komponent's own onLoad would
     * run once at mount and never again).
     */
    protected function snapToBottomOnLoad(bool $smooth = false)
    {
        if (!$this->id) {
            throw new \LogicException(static::class . ' needs an id before building the snap-to-bottom element (set $id or call $this->id() in created()).');
        }

        return ChatScripts::snapToBottom('#' . $this->id, $smooth);
    }
}
