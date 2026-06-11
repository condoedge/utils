<?php

namespace Condoedge\Utils\Kompo\Chat;

/**
 * ChatBubbleRenderer - builds the kit's own/other chat bubbles.
 *
 * Pure view builder over already-resolved presentation data (no models, no auth, no app
 * services). The markup, radii, shadows and entrance animations mirror the ai package's
 * user/assistant bubbles; colors flow through CSS custom properties (see chat-kit.scss:
 * --chat-bubble-own-bg, --chat-bubble-other-bg, ...) so each consumer themes in scss.
 *
 * Content semantics: string content is treated as HTML and rendered as-is. Escape plain
 * text with e() on the caller side (and add 'whitespace-pre-wrap' if line breaks matter),
 * or pass a ready Kompo element instead.
 */
class ChatBubbleRenderer
{
    /**
     * Bubble for the current user's messages (right-aligned, accent background).
     *
     * @param mixed       $content    HTML string, Kompo element, or array of elements.
     * @param string|null $authorName Shown above the bubble (right-aligned) when given.
     * @param mixed       $avatar     HTML string or Kompo element, rendered right of the bubble.
     * @param string|null $timestamp  Preformatted time string (e.g. '3:24 PM').
     * @param mixed       $footer     Element(s) rendered beside the timestamp (e.g. read-receipt avatars).
     * @param bool        $unread     Highlight ring for unread messages.
     * @param bool        $pending    Optimistic/sending style (reduced opacity).
     * @param bool        $animate    Entrance animation (use for newly created messages only).
     */
    public function ownBubble($content, ?string $authorName = null, $avatar = null, ?string $timestamp = null, $footer = null, bool $unread = false, bool $pending = false, bool $animate = false)
    {
        $animationClass = $animate ? ' animate-message-user' : '';
        $highlightClass = $animate ? ' animate-new-message-highlight' : '';

        return _Rows(
            _FlexEnd(
                _Rows(
                    $this->authorRow($authorName, true),
                    _Rows(
                        $this->contentElement($content),
                        $this->timestampRow($timestamp, $footer, true),
                    )->class($this->ownBubbleClass() . $highlightClass . $this->stateClasses($unread, $pending)),
                ),
                $avatar ? $this->avatarElement($avatar, 'ml-3 flex-shrink-0') : null,
            )->class('items-end' . $animationClass),
        )->class('group chat-bubble-row');
    }

    /**
     * Bubble for other participants' messages (left-aligned, neutral background).
     *
     * Same parameters as ownBubble(); the avatar renders left of the bubble and the
     * author name is left-aligned.
     */
    public function otherBubble($content, ?string $authorName = null, $avatar = null, ?string $timestamp = null, $footer = null, bool $unread = false, bool $pending = false, bool $animate = false)
    {
        $animationClass = $animate ? ' animate-message-assistant' : '';

        return _Rows(
            _Flex(
                $avatar ? $this->avatarElement($avatar, 'mr-3 flex-shrink-0 self-start') : null,
                _Rows(
                    $this->authorRow($authorName, false),
                    _Rows(
                        $this->contentElement($content),
                        $this->timestampRow($timestamp, $footer, false),
                    )->class($this->otherBubbleClass() . $this->stateClasses($unread, $pending)),
                ),
            )->class('items-start' . $animationClass),
        )->class('group chat-bubble-row');
    }

    /**
     * Raw HTML template of an own bubble for client-side optimistic rendering. $HTML and
     * $TIME are substituted by chat-kit.js at send time; the pending style is baked in so
     * the temp bubble reads as "sending" until the panel refresh replaces it.
     *
     * @param string|null $authorName   Escaped and shown above the bubble when given.
     * @param string|null $avatarHtml   Trusted HTML, rendered right of the bubble when given.
     * @param string      $contentClass Extra classes on the content div (e.g. 'ck ck-content leading-relaxed').
     */
    public function ownBubbleTemplate(?string $authorName = null, ?string $avatarHtml = null, string $contentClass = ''): string
    {
        $authorRow = $authorName
            ? '<div class="flex items-center mb-1 justify-end"><span class="text-sm font-medium chat-bubble-author">' . e($authorName) . '</span></div>'
            : '';

        $avatar = $avatarHtml
            ? '<div class="ml-3 flex-shrink-0">' . $avatarHtml . '</div>'
            : '';

        return '<div class="group chat-bubble-row">'
            . '<div class="vlFlex flex justify-end items-end animate-message-user">'
                . '<div>'
                    . $authorRow
                    . '<div class="' . $this->ownBubbleClass() . ' chat-bubble-pending animate-new-message-highlight">'
                        . '<div class="chat-bubble-content' . ($contentClass !== '' ? ' ' . $contentClass : '') . '">$HTML</div>'
                        . '<div class="flex mt-2 items-center justify-end gap-2"><span class="text-xs opacity-60">$TIME</span></div>'
                    . '</div>'
                . '</div>'
                . $avatar
            . '</div>'
        . '</div>';
    }

    /* SHARED PIECES */

    protected function ownBubbleClass(): string
    {
        return 'group px-4 py-3 rounded-2xl rounded-tr-md max-w-xl chat-bubble-own shadow-md';
    }

    protected function otherBubbleClass(): string
    {
        return 'group px-5 py-4 rounded-2xl rounded-tl-md max-w-2xl chat-bubble-other shadow-sm hover:shadow-md transition-shadow';
    }

    protected function stateClasses(bool $unread, bool $pending): string
    {
        return ($unread ? ' chat-bubble-unread' : '') . ($pending ? ' chat-bubble-pending' : '');
    }

    protected function authorRow(?string $authorName, bool $own)
    {
        if (!$authorName) {
            return null;
        }

        return _Flex(
            _Html(e($authorName))->class('text-sm font-medium chat-bubble-author'),
        )->class('items-center mb-1' . ($own ? ' justify-end' : ''));
    }

    protected function timestampRow(?string $timestamp, $footer, bool $own)
    {
        if (!$timestamp && !$footer) {
            return null;
        }

        return _Flex(
            $timestamp ? _Html(e($timestamp))->class('text-xs ' . ($own ? 'opacity-60' : 'text-gray-400')) : null,
            $footer,
        )->class('mt-2 items-center justify-end gap-2');
    }

    protected function contentElement($content)
    {
        if (is_string($content)) {
            return _Html($content)->class('chat-bubble-content');
        }

        if (is_array($content)) {
            return _Rows(...$content)->class('chat-bubble-content');
        }

        return $content ? $content->class('chat-bubble-content') : null;
    }

    protected function avatarElement($avatar, string $class)
    {
        if (is_string($avatar)) {
            return _Html($avatar)->class($class);
        }

        return $avatar->class($class);
    }
}
