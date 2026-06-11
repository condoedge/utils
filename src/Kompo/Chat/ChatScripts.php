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
