<?php

namespace Condoedge\Utils\Kompo\Chat;

/**
 * ChatScripts - injects the shared chat-kit JS library and wires per-instance behaviors.
 *
 * The library (resources/js/chat-kit.js) is self-guarded (window.KompoChatKit), so every
 * carrier embeds it and only the first execution on a page defines it. No asset pipeline:
 * the JS travels inside _Hidden()->onLoad(...) carrier elements - the 'load' interaction is
 * only allowed on a _Hidden() carrier, never on _Rows/_Flex wrappers.
 *
 * Per-instance configuration is passed as a JSON argument to window.KompoChatKit methods
 * (json_encode owns the escaping) - no str_replace template splicing into the JS source.
 */
class ChatScripts
{
    protected static ?string $libJs = null;

    /**
     * Carrier element that injects the chat-kit library without binding anything.
     * Optional convenience: initComposer()/snapToBottom() embed the guarded library themselves.
     */
    public static function core()
    {
        return _Hidden()->onLoad(fn($e) => $e->run('() => { ' . static::libJs() . ' }'));
    }

    /**
     * Carrier element that binds the composer behaviors (enter-to-send + optimistic append).
     *
     * Render it INSIDE the composer form tree: when the messages panel refreshes, the form
     * remounts and this carrier re-fires, rebinding against the fresh DOM (binding is
     * idempotent per DOM element via a dataset flag).
     *
     * @param array $config See ChatComposerForm::composerConfig() and chat-kit.js bindComposer().
     */
    public static function initComposer(array $config)
    {
        $json = static::encode($config);

        return _Hidden()->onLoad(
            fn($e) => $e->run('() => { ' . static::libJs() . ' window.KompoChatKit.bindComposer(' . $json . '); }')
        );
    }

    /**
     * Carrier element that snaps a messages panel to its visual bottom (newest message).
     *
     * Place it in the Query's bottom(): a Query komponent's own onLoad runs once at mount and
     * is then stripped, but elements inside top()/bottom() remount and re-fire their onLoad
     * on every ->refresh() - exactly when the snap must re-run.
     */
    public static function snapToBottom(string $panelSelector, bool $smooth = false)
    {
        $args = static::encode($panelSelector) . ', ' . ($smooth ? 'true' : 'false');

        return _Hidden()->onLoad(
            fn($e) => $e->run('() => { ' . static::libJs() . ' window.KompoChatKit.snapToBottom(' . $args . '); }')
        );
    }

    /**
     * "X is typing..." indicator: hidden by default, shown when a typing whisper
     * arrives on the (already-authorized) private channel, self-hides after 3s.
     * Pair with ChatComposerForm::typingWhisperChannel()/typingWhisperEvent().
     */
    public static function typingIndicator(string $channel, string $eventName = 'typing', ?string $label = null, int $hideAfterMs = 4000)
    {
        return _Flex(
            _Html('<span></span><span></span><span></span>')->class('chat-typing-dots'),
            // The [data-whisper-name] slot is filled with the typist's name from the
            // whisper payload ("John is typing...")
            _Html('<span data-whisper-name class="font-medium"></span><span>'.e($label ?: __('chat.typing')).'</span>')
                ->class('text-xs text-gray-500'),
        )
        // Starts hidden: showOnWhisper only ever REMOVES the 'hidden' class.
        // hideAfter must exceed the sender's whisper throttle (default 2500ms)
        // so the indicator stays steady while typing continues.
        ->class('chat-typing-indicator items-center gap-2 px-6 py-1 hidden')
        ->showOnWhisper($channel, $eventName)
        ->hideAfter($hideAfterMs);
    }

    /**
     * App-wide "new message" toast listener (bottom-right, self-dismissing). Mount it
     * once in the host's layout (e.g. the navbar komponent); it listens on a personal
     * private channel so only actual recipients ever receive the payload.
     *
     * @param array $config See chat-kit.js bindMessageToasts(): channel, event,
     *                      titleText, urlTemplate ('__ID__' placeholder), suppressPathPrefix, durationMs.
     */
    public static function messageToastListener(array $config)
    {
        $json = static::encode($config);

        return _Hidden()->onLoad(
            fn($e) => $e->run('() => { ' . static::libJs() . ' window.KompoChatKit.bindMessageToasts(' . $json . '); }')
        );
    }

    /**
     * Marks a chat channel as "on screen" so the toast listener suppresses its own
     * notifications. Place inside the chat column: it re-fires on every mount/switch.
     */
    public static function setOpenChatChannel($channelId)
    {
        $id = static::encode($channelId);

        return _Hidden()->onLoad(
            fn($e) => $e->run('() => { ' . static::libJs() . ' window.KompoChatKit.setOpenChatChannel(' . $id . '); }')
        );
    }

    /**
     * The chat-kit library source, read once per request. The source itself is guarded
     * (window.KompoChatKit), so executing it more than once on a page is a no-op.
     */
    public static function libJs(): string
    {
        if (static::$libJs === null) {
            static::$libJs = file_get_contents(__DIR__ . '/../../../resources/js/chat-kit.js');
        }

        return static::$libJs;
    }

    protected static function encode($value): string
    {
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }
}
