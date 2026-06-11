/**
 * KompoChatKit - shared chat UI behaviors for Kompo chat surfaces.
 *
 * Injected by PHP (Condoedge\Utils\Kompo\Chat\ChatScripts) through _Hidden()->onLoad(...)
 * carrier elements - no asset pipeline. The whole file is self-guarded so embedding it in
 * several carriers on one page defines it exactly once.
 *
 * Layout contract (Kompo Query, paginationType 'Scroll' + topPagination):
 * - The scroll container is the panel's '.vlQueryWrapper'; the framework owns scrollTop
 *   semantics there (never column-reverse the wrapper itself).
 * - The items layout root is '.vlQueryWrapper > div' and renders flex-col-reverse, so the
 *   FIRST DOM child is the visual bottom. Optimistic nodes are PREPENDED there and are
 *   wiped automatically by the next refresh/browse (the items subtree is re-rendered).
 */
(function () {
    'use strict';

    if (window.KompoChatKit) return;

    var escapeHtml = function (text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    };

    /**
     * Input adapters. Each knows how to find its editable, read its content as HTML,
     * tell whether it holds text, and recognize keydown events originating from it.
     */
    var adapters = {
        ckeditor: {
            editable: function (form) {
                return form.querySelector('.ck-editor__editable');
            },
            getHtml: function (form) {
                var ed = adapters.ckeditor.editable(form);
                if (!ed) return '';
                // CKEditor 5 exposes its instance on the editable element
                if (ed.ckeditorInstance && ed.ckeditorInstance.getData) {
                    return ed.ckeditorInstance.getData();
                }
                return ed.innerHTML || '';
            },
            hasText: function (form) {
                var ed = adapters.ckeditor.editable(form);
                return (((ed && ed.textContent) || '').trim().length) > 0;
            },
            ownsEvent: function (e) {
                return !!(e.target.closest && e.target.closest('.ck-editor__editable'));
            },
            clear: function (form) {
                var ed = adapters.ckeditor.editable(form);
                if (ed && ed.ckeditorInstance && ed.ckeditorInstance.setData) {
                    ed.ckeditorInstance.setData('');
                }
            },
        },
        textarea: {
            editable: function (form) {
                return form.querySelector('textarea');
            },
            getHtml: function (form) {
                var el = adapters.textarea.editable(form);
                // Plain text: escape, then keep line breaks visible in the HTML bubble
                return el ? escapeHtml(el.value).replace(/\n/g, '<br>') : '';
            },
            hasText: function (form) {
                var el = adapters.textarea.editable(form);
                return (((el && el.value) || '').trim().length) > 0;
            },
            ownsEvent: function (e) {
                return !!(e.target.closest && e.target.closest('textarea'));
            },
            clear: function (form) {
                var el = adapters.textarea.editable(form);
                if (!el) return;
                el.value = '';
                el.dispatchEvent(new Event('input', { bubbles: true }));
            },
        },
    };

    window.KompoChatKit = {

        /**
         * Snap a messages panel to its visual bottom (newest message).
         * @param {string} panelSelector css selector of the Query komponent (e.g. '#chat-messages-panel')
         * @param {boolean} smooth
         */
        snapToBottom: function (panelSelector, smooth) {
            var panel = document.querySelector(panelSelector);
            var wrapper = panel && panel.querySelector('.vlQueryWrapper');
            if (!wrapper) return;

            setTimeout(function () {
                wrapper.scrollTo({ top: wrapper.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
            }, 100);
        },

        /**
         * Prepend an optimistic temp node inside the items layout root (visual bottom under
         * flex-col-reverse). The node self-expires (default 20s) so a failed request never
         * leaves a ghost bubble; on success the panel refresh wipes it earlier. Kompo's
         * native error handling stays untouched (no PHP onError on the submit).
         *
         * @param {object} config { panelSelector, expireMs? }
         * @param {string} html   ready-to-insert bubble HTML
         * @returns {HTMLElement|null}
         */
        appendOptimistic: function (config, html) {
            var itemsRoot = document.querySelector(config.panelSelector + ' .vlQueryWrapper > div');
            if (!itemsRoot) return null;

            // Empty panel: the items slot holds the no-items component instead of the
            // cards; hide its message so the temp bubble doesn't render beside it
            // (the next refresh re-renders the slot and restores whatever is correct)
            var noItems = itemsRoot.querySelector('.vlNoItems');
            if (noItems) noItems.style.display = 'none';

            var node = document.createElement('div');
            node.className = 'chat-temp-message';
            node.setAttribute('data-chat-temp', 'true');
            node.innerHTML = html;
            itemsRoot.prepend(node);

            setTimeout(function () { node.remove(); }, config.expireMs || 20000);

            this.snapToBottom(config.panelSelector, false);

            return node;
        },

        /**
         * Wire a composer form: capture-phase Enter-to-send + optimistic append on send.
         *
         * config = {
         *   formId: string,                    // DOM id of the composer root element
         *   panelSelector: string,             // css selector of the messages Query panel
         *   sendButtonSelector: string,        // selector of the send/submit button inside the form
         *   inputType: 'ckeditor'|'textarea',  // input adapter
         *   enterToSend: boolean,              // default true
         *   optimistic: boolean,               // append the temp bubble on send
         *   bubbleTemplate: string|null,       // own-bubble HTML with $HTML / $TIME placeholders
         *   requireContentSelectors: string[], // extra "has content" probes (e.g. file chips)
         *   expireMs: number,                  // temp bubble self-expiry (default 20000)
         * }
         *
         * Binding is idempotent per DOM element (dataset flag); after a panel refresh the
         * composer remounts as a fresh element and the carrier re-fires, rebinding cleanly.
         */
        bindComposer: function (config) {
            var form = document.getElementById(config.formId);
            if (!form || form.dataset.chatKitBound) return;
            form.dataset.chatKitBound = '1';

            var adapter = adapters[config.inputType] || adapters.ckeditor;
            var sendSelector = config.sendButtonSelector || 'button[type=submit]';
            var extraSelectors = config.requireContentSelectors || [];

            var hasExtraContent = function () {
                return extraSelectors.some(function (sel) { return !!form.querySelector(sel); });
            };
            var hasContent = function () {
                return adapter.hasText(form) || hasExtraContent();
            };

            var appendOptimistic = function () {
                if (!config.optimistic || !config.bubbleTemplate) return;
                if (!adapter.hasText(form)) return; // attachment-only sends get no text bubble

                var html = adapter.getHtml(form);
                var time = new Date().toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: !!config.timeHour12,
                });

                // Function replacements avoid $-pattern substitution bugs with user
                // content; $TIME goes first so a literal '$TIME' typed in the message
                // can't hijack the timestamp slot
                var bubble = config.bubbleTemplate
                    .replace('$TIME', function () { return time; })
                    .replace('$HTML', function () { return html; });

                window.KompoChatKit.appendOptimistic(config, bubble);
            };

            // Fires for both real clicks and the programmatic click from Enter. Capture phase
            // so the content is read BEFORE Kompo serializes/submits (the editor is never
            // cleared here - the composer remounts after the panel refresh anyway).
            form.addEventListener('click', function (e) {
                var btn = e.target.closest ? e.target.closest(sendSelector) : null;
                if (!btn || btn.disabled) return;
                if (!hasContent()) return;
                appendOptimistic();

                if (config.clearOnSend !== false && adapter.clear) {
                    // Kompo serializes the form synchronously during this same click
                    // dispatch, so clearing on the next tick cannot empty the payload
                    setTimeout(function () { adapter.clear(form); }, 50);
                }
            }, true);

            if (config.enterToSend !== false) {
                // Capture phase beats CKEditor's internal Enter handler; Shift+Enter and IME
                // composition keep their native behavior (new line / compose).
                form.addEventListener('keydown', function (e) {
                    if (e.key !== 'Enter' || e.shiftKey || e.isComposing) return;

                    // Kompo buttons are type=button, so with no submit button the browser's
                    // implicit submission on Enter in a lone text input (e.g. the subject
                    // field) would do a native GET navigation, losing the composed message
                    if (e.target.matches && e.target.matches('input:not([type=hidden]):not([type=file]):not([type=checkbox]):not([type=radio])')) {
                        e.preventDefault();
                        return;
                    }

                    if (!adapter.ownsEvent(e)) return;

                    e.preventDefault();
                    e.stopPropagation();

                    if (!hasContent()) return;

                    var btn = form.querySelector(sendSelector);
                    if (!btn) return;

                    if (!btn.disabled) {
                        btn.click();
                        return;
                    }

                    // A previous send is still posting: queue this one so it fires the
                    // moment the button re-enables (sends pipeline while the user types;
                    // the editor content is serialized at the deferred click, so it is
                    // NOT cleared until then)
                    if (form.dataset.chatKitDeferred) return;
                    form.dataset.chatKitDeferred = '1';

                    var waited = 0;
                    (function waitAndSend() {
                        var b = form.querySelector(sendSelector);
                        if (!b || waited > 15000) {
                            delete form.dataset.chatKitDeferred;
                            return;
                        }
                        if (b.disabled) {
                            waited += 100;
                            return setTimeout(waitAndSend, 100);
                        }
                        delete form.dataset.chatKitDeferred;
                        b.click();
                    })();
                }, true);
            }
        },
    };
})();
