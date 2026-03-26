// index.js — Step Builder v2 Entry Point
// IIFE auto-init pattern preserved for backward compatibility

import * as stateModule from './state';
import * as events from './events';
import { injectStyles } from './styles';
import * as shortcuts from './shortcuts';
import * as codegen from './codegen';
import { el, flashStatus, copyToClipboard } from './helpers';
import { ICONS, iconEl } from './icons';
import { createSidebar } from './ui/sidebar';
import * as cards from './ui/cards';
import * as elementPicker from './ui/element-picker';
import * as stepList from './ui/step-list';
import * as historyDrawer from './ui/history-drawer';
import * as pathPreview from './ui/path-preview';

// --- Card imports (self-registering, order = display order) ---
import './cards/position-card';
import './cards/cursor-card';
import './cards/highlight-card';
import './cards/content-card';
import './cards/conditions-card';
import './cards/hover-card';
import './cards/scroll-card';
import './cards/options-card';
import './cards/actions-card';
import './cards/advance-card';
import './cards/global-opts-card';

export default (function() {
    'use strict';

    function _waitForEngine() {
        if (typeof TutorialEngine === 'undefined' || !TutorialEngine._onReady) {
            setTimeout(_waitForEngine, 50);
            return;
        }
        _initStepBuilder();
    }

    function _initStepBuilder() {
        TutorialEngine._onReady(function(ctx) {

            // --- Init foundation ---
            var styleEl = injectStyles();
            stateModule.init(ctx);

            // --- Build sidebar ---
            var sidebar = createSidebar();
            var panel = sidebar.panel;
            document.body.appendChild(panel);

            // --- Init modules ---
            elementPicker.init();
            pathPreview.init();
            cards.init(sidebar.detailZone);
            stepList.init(sidebar.stepListZone);
            historyDrawer.init(sidebar.historyDrawer);
            cards.renderCards();

            // --- Adjust overlay to not overlap sidebar ---
            function adjustOverlay() {
                var w = panel.offsetWidth;
                ctx.overlay.style.width = 'calc(100% - ' + w + 'px)';
            }
            adjustOverlay();
            events.on('sidebar-collapsed', function(data) {
                ctx.overlay.style.width = data.collapsed ? '100%' : 'calc(100% - ' + panel.offsetWidth + 'px)';
            });
            events.on('sidebar-resized', function(data) {
                ctx.overlay.style.width = 'calc(100% - ' + data.width + 'px)';
            });

            // --- Init shortcuts ---
            shortcuts.init(panel);

            // --- Spectator mode ---
            var _spectatorActive = false;
            var _spectatorExitBtn = null;
            var _savedDevMode = false;

            function enterSpectator() {
                if (_spectatorActive) return;
                _spectatorActive = true;
                _savedDevMode = !!window._tutorialDevMode;

                // Hide sidebar, disable dev mode
                sidebar.collapse();
                panel.style.display = 'none';
                ctx.overlay.style.width = '100%';
                document.body.style.marginRight = '';
                window._tutorialDevMode = false;
                shortcuts.setActive(false);

                // Restart from step 0
                ctx.showStep(0);

                // Floating exit button
                _spectatorExitBtn = el('button', {
                    className: 'sb-spectator-exit',
                    textContent: 'Quitter le mode spectateur (Esc)',
                });
                _spectatorExitBtn.addEventListener('click', exitSpectator);
                document.body.appendChild(_spectatorExitBtn);

                // Listen for Escape
                document.addEventListener('keydown', _spectatorEsc);
            }

            function exitSpectator() {
                if (!_spectatorActive) return;
                _spectatorActive = false;

                // Restore sidebar
                panel.style.display = '';
                sidebar.expand();
                document.body.style.marginRight = panel.offsetWidth + 'px';
                ctx.overlay.style.width = 'calc(100% - ' + panel.offsetWidth + 'px)';
                window._tutorialDevMode = _savedDevMode;
                shortcuts.setActive(true);

                // Remove exit button
                if (_spectatorExitBtn && _spectatorExitBtn.parentNode) {
                    _spectatorExitBtn.parentNode.removeChild(_spectatorExitBtn);
                }
                _spectatorExitBtn = null;
                document.removeEventListener('keydown', _spectatorEsc);

                // Return to selected step
                ctx.showStep(stateModule.getSelectedIndex());
            }

            function _spectatorEsc(e) {
                if (e.key === 'Escape') exitSpectator();
            }

            // Add spectator button to header
            var spectatorBtn = el('button', { className: 'sb-btn sb-btn-ghost sb-btn-icon', style: { marginLeft: 'auto' } });
            spectatorBtn.appendChild(iconEl('spectator', 14));
            spectatorBtn.title = 'Spectator mode';
            spectatorBtn.addEventListener('click', enterSpectator);
            sidebar.header.insertBefore(spectatorBtn, sidebar.header.lastElementChild);

            // --- Screenshot capture ---
            events.on('capture-screenshot', function(data) {
                try {
                    panel.style.display = 'none';
                    document.body.style.marginRight = '';
                    setTimeout(function() {
                        if (typeof html2canvas !== 'undefined') {
                            html2canvas(document.body, { scale: 0.3, logging: false }).then(function(canvas) {
                                stateModule.setScreenshot(data.index, canvas.toDataURL('image/jpeg', 0.5));
                            }).finally(function() {
                                panel.style.display = '';
                                document.body.style.marginRight = panel.offsetWidth + 'px';
                            });
                        } else {
                            panel.style.display = '';
                            document.body.style.marginRight = panel.offsetWidth + 'px';
                            flashStatus('html2canvas not available');
                        }
                    }, 100);
                } catch(e) {
                    panel.style.display = '';
                    document.body.style.marginRight = panel.offsetWidth + 'px';
                }
            });

            // --- Sync step highlight when tutorial navigates ---
            ctx.overlay.addEventListener('tutorial-step-change', function(e) {
                if (e.detail && typeof e.detail.index === 'number') {
                    stateModule.syncSelectedIndex(e.detail.index);
                }
            });

            // --- Cleanup when tutorial overlay is removed ---
            var origRemove = ctx.overlay.remove.bind(ctx.overlay);
            ctx.overlay.remove = function() {
                if (_spectatorActive) exitSpectator();
                shortcuts.destroy();
                elementPicker.cancel();
                pathPreview.destroy();
                if (panel.parentNode) panel.parentNode.removeChild(panel);
                if (styleEl.parentNode) styleEl.parentNode.removeChild(styleEl);
                document.body.style.marginRight = '';
                origRemove();
            };

        });
    }

    _waitForEngine();
})();
