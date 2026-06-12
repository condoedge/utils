<?php

namespace Condoedge\Utils\Kompo\Chat;

use Condoedge\Utils\Kompo\Common\Form;

/**
 * ChatComposerForm - base Form for a chat composer (attachment slot + input + send button).
 *
 * Renders the composer row in the kit's chat style and wires the shared JS through a
 * _Hidden carrier (ChatScripts::initComposer): capture-phase Enter-to-send and the
 * optimistic temp bubble prepended into the messages panel's items root.
 *
 * Contracts the wiring relies on:
 * - The send button stays a plain submit (no PHP ->onError(...): any PHP error interaction
 *   replaces Kompo's native error handling). Failed sends are covered by the temp bubble's
 *   ~20s self-expiry instead.
 * - The input is cleared 50ms AFTER the send click (never before/at it): Kompo serializes
 *   the form synchronously during the click dispatch, so the next-tick clear can't empty
 *   the payload, and the user can keep typing the next message right away.
 *
 * Consumers implement composerInput() and panelSelector(), and override composerId() when
 * several composers can coexist on a page.
 */
abstract class ChatComposerForm extends Form
{
    public const INPUT_CKEDITOR = 'ckeditor';
    public const INPUT_TEXTAREA = 'textarea';

    public $class = 'w-full';

    /* ABSTRACT */

    /**
     * The input element (e.g. _CKEditor()->name('html')->class('chat-composer-input mb-0 flex-1 min-w-0')
     * or _Textarea()->name('message')). Match composerInputType() to what is returned here.
     */
    abstract protected function composerInput();

    /**
     * Css selector of the messages Query panel this composer feeds (e.g. '#chat-messages-panel').
     * Used by the optimistic append and as the default ->refresh() target of the send button.
     */
    abstract protected function panelSelector(): string;

    /* RENDER */

    public function render()
    {
        if ($this->backgroundSendEnabled()) {
            $this->selfMethods(['sendChatMessage']);
        }

        // Komponent id so the classic (attachment) send can refresh/remount this
        // composer, which is what clears the file chips and resets the editor
        $this->id($this->composerId());

        $input = $this->composerInput();

        if ($channel = $this->typingWhisperChannel()) {
            // Client-to-client whisper on the already-authorized private channel;
            // the messages panel shows the kit typing indicator on the same event
            // ("John is typing..." — the name travels in the whisper payload)
            $input = $input->whisperOnInput($channel, $this->typingWhisperEvent(), [
                'name' => auth()->user()?->name,
            ]);
        }

        return _Rows(
            $this->topSlot(),
            _Flex(
                $this->attachmentElement(),
                $input,
                $this->sendButton(),
            )->class($this->composerRowClass()),
            $this->bottomSlot(),
            // Consumers that run their own send pipeline (e.g. the ai chat) disable every
            // kit JS behavior; binding an inert listener would be noise, so skip the carrier
            $this->kitBindingEnabled() ? ChatScripts::initComposer($this->composerConfig()) : null,
        )->class($this->composerClass())
        ->id($this->composerId());
    }

    /**
     * Private channel to whisper typing events on (null disables). Pair with
     * ChatScripts::typingIndicator($channel, $event) near the messages panel.
     */
    protected function typingWhisperChannel(): ?string
    {
        return null;
    }

    protected function typingWhisperEvent(): string
    {
        return 'typing';
    }

    /* BACKGROUND SEND (JS Bridge) */

    /**
     * When enabled, sends go through selfPost('sendChatMessage') with the payload read
     * DIRECTLY from the editor at send time (immune to the field's debounced model -
     * fast typists were losing the tail of their message) and the temp bubble is a real
     * Query item swapped for the persisted card by the response. Sends run in parallel:
     * nothing disables, nothing remounts, the user keeps typing.
     * Attachment sends fall back to the classic full-form submit (multipart).
     */
    protected function backgroundSendEnabled(): bool
    {
        return true;
    }

    /**
     * Persist one chat message and return the rendered message card (the same element
     * the messages Query renders for an item). Receives the JS payload:
     * ['html' => ..., 'subject' => ..., 'tempId' => ...].
     */
    protected function persistChatMessage(array $payload)
    {
        throw new \LogicException('Implement persistChatMessage() or disable backgroundSendEnabled().');
    }

    public function sendChatMessage()
    {
        return $this->persistChatMessage(request()->all());
    }

    protected function sendScript(): string
    {
        $config = json_encode([
            'formId' => $this->composerId(),
            'panelId' => $this->refreshTargetId(),
            'panelSelector' => $this->panelSelector(),
            'bubbleTemplate' => $this->optimisticBubbleHtml(),
            // A real serialized element, so the Vue layer gets the exact shape it
            // expects (hand-written {component: ...} specs render as <Vlundefined>)
            'tempItemSpec' => json_decode(json_encode(_Html('')->class('chat-temp-message')), true),
            'timeHour12' => $this->optimisticTimeHour12(),
            'requireContentSelectors' => $this->requireContentSelectors(),
            'failedText' => __('chat.send-failed'),
            'uploadingText' => __('chat.sending'),
            'expireMs' => $this->optimisticExpireMs(),
        ]);

        // Plain (non-async) arrow on the outside: vue-kompo's run() arrow-function
        // detector doesn't recognize an "async" prefix; the async work runs in an IIFE
        return '(ctx) => { (async () => {
            const cfg = '.$config.';
            const form = document.getElementById(cfg.formId);
            if (!form) return;

            const ed = form.querySelector(".ck-editor__editable");
            const inst = ed && ed.ckeditorInstance;
            const html = ((inst && inst.getData) ? inst.getData() : (ed ? ed.innerHTML : "")) || "";
            const text = ed ? (ed.textContent || "").trim() : "";
            const hasFiles = (cfg.requireContentSelectors || []).some(s => !!form.querySelector(s));

            if (!text && !hasFiles) return;

            const esc = (s) => { const d = document.createElement("div"); d.textContent = s == null ? "" : String(s); return d.innerHTML; };
            const tempId = "chat-temp-" + Date.now() + "-" + Math.random().toString(36).slice(2, 7);
            const time = new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit", hour12: !!cfg.timeHour12 });
            const makeSpec = (contentHtml) => {
                const spec = JSON.parse(JSON.stringify(cfg.tempItemSpec));
                spec.label = cfg.bubbleTemplate.replace("$TIME", () => time).replace("$HTML", () => contentHtml);
                return spec;
            };

            if (hasFiles) {
                // Attachments need a multipart form submit: classic path — but show an
                // optimistic placeholder immediately (local thumbnails for images) so
                // the user sees the upload is underway
                const classic = form.querySelector(".chat-send-classic");
                if (!classic) return;

                let thumbs = "";
                const fileInput = form.querySelector("input[type=file]");
                if (fileInput && fileInput.files) {
                    Array.from(fileInput.files).forEach((f) => {
                        if (f.type && f.type.indexOf("image/") === 0) {
                            thumbs += \'<img src="\' + URL.createObjectURL(f) + \'" class="chat-attachment-image rounded-xl object-cover" />\';
                        }
                    });
                }
                const names = Array.from(form.querySelectorAll(".chat-composer-upload .vlCustomLabel, .discussion-composer-upload .vlCustomLabel"))
                    .map((n) => (n.textContent || "").trim()).filter(Boolean);

                const placeholder = (text ? html : "")
                    + (thumbs ? \'<div class="flex flex-wrap gap-2 mt-1">\' + thumbs + "</div>" : "")
                    + \'<div class="text-xs opacity-75 mt-1">\' + esc(names.join(", ")) + (names.length ? " &mdash; " : "") + esc(cfg.uploadingText) + "</div>";

                ctx.$k.query(cfg.panelId).prepend(makeSpec(placeholder), tempId);
                if (window.KompoChatKit) window.KompoChatKit.snapToBottom(cfg.panelSelector, false);

                // The panel refresh after the upload wipes the placeholder; if the
                // upload fails it self-expires (uploads are slow: triple the budget)
                setTimeout(() => { try { ctx.$k.query(cfg.panelId).remove(tempId); } catch (e) {} }, (cfg.expireMs || 20000) * 3);

                classic.click();
                return;
            }

            const spec = makeSpec(html);

            ctx.$k.query(cfg.panelId).prepend(spec, tempId);
            if (window.KompoChatKit) window.KompoChatKit.snapToBottom(cfg.panelSelector, false);

            // Payload is already captured: clear right away so the next message can be typed
            if (inst && inst.setData) inst.setData("");

            const subjectInput = form.querySelector("input[name=subject]");
            const subject = subjectInput ? subjectInput.value : null;
            if (subjectInput) subjectInput.value = "";

            try {
                await ctx.selfPost("sendChatMessage", { html: html, subject: subject, tempId: tempId })
                    .updateInQuery(cfg.panelId, tempId);
            } catch (e) {
                // Retract the bubble, restore the draft, surface the error
                ctx.$k.query(cfg.panelId).remove(tempId);
                if (inst && inst.setData) inst.setData(html);
                if (subjectInput && subject) subjectInput.value = subject;
                ctx.$k.alert(cfg.failedText, "error", { alertClass: "vlAlertError" });
            }
        })(); }';
    }

    /* SLOTS & LOOK (overridable) */

    /**
     * Optional element above the composer row (subject input, replying-to note...).
     */
    protected function topSlot()
    {
        return null;
    }

    /**
     * Optional element below the composer row (quick-action chips, hints, counters...).
     */
    protected function bottomSlot()
    {
        return null;
    }

    /**
     * Optional attachment control left of the input (e.g. _MultiFile()->name('files')
     * ->class('chat-composer-upload mb-0 shrink-0')). Null hides the slot.
     */
    protected function attachmentElement()
    {
        return null;
    }

    protected function sendButton()
    {
        if (!$this->backgroundSendEnabled()) {
            return $this->classicSendButton()->class('chat-send-button mb-0 shrink-0');
        }

        return _Rows(
            _Button($this->sendButtonLabel())
                ->class('chat-send-button mb-0')
                // selfMethods macro: the run() context resolves the selfPost allowlist
                // from the TRIGGERING element's config, so it must live on the button
                ->selfMethods(['sendChatMessage'])
                ->onClick->run($this->sendScript()),

            // Hidden fallback for attachment sends (multipart form submit + panel refresh)
            $this->classicSendButton()->class('chat-send-classic hidden'),
        )->class('shrink-0');
    }

    protected function classicSendButton()
    {
        // Refreshes the composer too: it lives outside the messages Query, so this
        // remount is what clears the attachment chips after a file send
        return _SubmitButton($this->sendButtonLabel())
            ->refresh([$this->refreshTargetId(), $this->composerId()]);
    }

    protected function sendButtonLabel(): string
    {
        return 'chat.send';
    }

    /**
     * Komponent id the send button refreshes after a successful save. Defaults to the
     * messages panel; override when panelSelector() is not a plain '#id' selector.
     */
    protected function refreshTargetId(): string
    {
        return ltrim($this->panelSelector(), '#');
    }

    /**
     * DOM id of the composer root - the element the JS binds against. MUST be unique per
     * page: override with an instance suffix when several composers can coexist.
     */
    protected function composerId(): string
    {
        return 'chat-composer-form';
    }

    protected function composerClass(): string
    {
        return 'chat-composer p-4 !pb-8 border-t border-gray-100/70';
    }

    /**
     * The pill row around attachment + input + send. The pill owns the single
     * border and focus ring; chat-kit.scss neutralizes both Kompo's field-wrapper
     * border and CKEditor's editable border inside it (no double border).
     */
    protected function composerRowClass(): string
    {
        return 'chat-composer-row items-end gap-2 px-3';
    }

    /* JS WIRING (overridable) */

    /**
     * Whether the composer needs the kit's JS binding at all. False when every behavior
     * bindComposer() can perform is disabled (enter-to-send, the JS optimistic bubble and
     * the post-send clear — the latter two are owned by sendScript() in background mode).
     */
    protected function kitBindingEnabled(): bool
    {
        return $this->enterToSendEnabled()
            || ($this->optimisticEnabled() && !$this->backgroundSendEnabled())
            || ($this->clearInputOnSend() && !$this->backgroundSendEnabled());
    }

    /**
     * The config handed to window.KompoChatKit.bindComposer() (json_encode owns escaping).
     */
    protected function composerConfig(): array
    {
        // With background send, the send button's run() script owns the optimistic
        // bubble and the input clearing; the kit binding only does Enter-to-send.
        $jsOptimistic = $this->optimisticEnabled() && !$this->backgroundSendEnabled();

        return [
            'formId' => $this->composerId(),
            'panelSelector' => $this->panelSelector(),
            'sendButtonSelector' => $this->sendButtonSelector(),
            'inputType' => $this->composerInputType(),
            'enterToSend' => $this->enterToSendEnabled(),
            'optimistic' => $jsOptimistic,
            'bubbleTemplate' => $jsOptimistic ? $this->optimisticBubbleHtml() : null,
            'requireContentSelectors' => $this->requireContentSelectors(),
            'expireMs' => $this->optimisticExpireMs(),
            'timeHour12' => $this->optimisticTimeHour12(),
            'clearOnSend' => $this->clearInputOnSend() && !$this->backgroundSendEnabled(),
        ];
    }

    /**
     * Clear the input right after each send (50ms after the click, once Kompo has
     * serialized the form) so the user can type the next message immediately.
     */
    protected function clearInputOnSend(): bool
    {
        return true;
    }

    /**
     * Clock format of the optimistic bubble's $TIME — match the consumer's persisted
     * timestamp format so the bubble doesn't visibly change when the refresh lands
     * (false = 24h like Carbon 'H:i', true = 12h AM/PM like 'g:i A').
     */
    protected function optimisticTimeHour12(): bool
    {
        return false;
    }

    /**
     * 'ckeditor' or 'textarea' - must match the element composerInput() returns.
     */
    protected function composerInputType(): string
    {
        return static::INPUT_CKEDITOR;
    }

    protected function enterToSendEnabled(): bool
    {
        return true;
    }

    protected function optimisticEnabled(): bool
    {
        return true;
    }

    /**
     * Own-bubble HTML with $HTML / $TIME placeholders, substituted client-side at send time.
     * Override to mirror the consumer's persisted bubble markup exactly.
     */
    protected function optimisticBubbleHtml(): string
    {
        return (new ChatBubbleRenderer())->ownBubbleTemplate(auth()->user()?->name);
    }

    /**
     * Extra selectors that count as "has content" besides the input text (e.g. selected
     * file chips: ['.chat-composer-upload .vlCustomLabel']). With content in any of these,
     * Enter/click still sends even when the text input is empty (the optimistic bubble is
     * skipped for text-less sends).
     */
    protected function requireContentSelectors(): array
    {
        return [];
    }

    /**
     * Selector of the send button inside the composer (clicked programmatically by
     * Enter-to-send, watched for the optimistic append).
     */
    protected function sendButtonSelector(): string
    {
        return 'button.chat-send-button';
    }

    /**
     * Self-expiry of the optimistic temp bubble: long enough for slow saves, short enough
     * that a failed request doesn't leave a ghost message.
     */
    protected function optimisticExpireMs(): int
    {
        return 20000;
    }
}
