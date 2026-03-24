/**
 * Tutorial Step Builder — Dev-only visual editor for tutorial step configurations.
 * Requires TutorialEngine to be loaded first.
 */
export default (function() {
    'use strict';

    // Defer until TutorialEngine is available (file load order is alphabetical/prepended)
    function _waitForEngine() {
        if (typeof TutorialEngine === 'undefined' || !TutorialEngine._onReady) {
            setTimeout(_waitForEngine, 50);
            return;
        }
        _initStepBuilder();
    }
    function _initStepBuilder() {

    TutorialEngine._onReady(function(ctx) {

        // Store initial opts for change detection in export
        var initialOpts = {
            bubbleMaxWidth: ctx.opts.bubbleMaxWidth,
            bubbleMinWidth: ctx.opts.bubbleMinWidth,
            bubbleFontSize: ctx.opts.bubbleFontSize,
        };

        // --- Deep copy steps and augment with advance field ---
        var originalSteps = JSON.parse(JSON.stringify(ctx.steps));
        var peSteps = JSON.parse(JSON.stringify(ctx.steps));
        peSteps.forEach(function(s) {
            if (s.autoNext) {
                s.advance = 'auto';
                s.autoNextDelay = (typeof s.autoNext === 'number') ? s.autoNext : 3;
            } else if (s.afterAnimation) {
                s.advance = 'afterAnimation';
            } else {
                s.advance = 'click';
            }
        });

        var selectedIndex = 0;

        // =============================================
        // CSS INJECTION (scoped styles via data attribute)
        // =============================================
        var styleEl = document.createElement('style');
        styleEl.textContent = [
            '[data-sb] { font-family: "Inter", "Segoe UI", system-ui, -apple-system, sans-serif; }',
            '[data-sb] *, [data-sb] *::before, [data-sb] *::after { box-sizing: border-box; }',

            // Inputs
            '[data-sb] input, [data-sb] select, [data-sb] textarea {',
            '  background: rgba(255,255,255,0.06); color: #e0e4ec; border: 1px solid rgba(255,255,255,0.1);',
            '  border-radius: 8px; padding: 10px 12px; font-size: 15px; font-family: inherit;',
            '  transition: border-color 0.2s, box-shadow 0.2s; outline: none; width: 100%;',
            '}',
            '[data-sb] input:focus, [data-sb] select:focus {',
            '  border-color: #6c8aff; box-shadow: 0 0 0 2px rgba(108,138,255,0.15);',
            '}',
            '[data-sb] input[type="number"] { width: 80px; }',
            '[data-sb] input[type="checkbox"] { width: 18px; height: 18px; accent-color: #6c8aff; cursor: pointer; }',
            '[data-sb] select { cursor: pointer; appearance: none; padding-right: 22px;',
            '  background-image: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'10\' height=\'6\'%3E%3Cpath d=\'M0 0l5 6 5-6z\' fill=\'%23888\'/%3E%3C/svg%3E");',
            '  background-repeat: no-repeat; background-position: right 8px center; }',

            // Buttons
            '[data-sb] .sb-btn {',
            '  border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600;',
            '  padding: 9px 16px; transition: all 0.15s; font-family: inherit; white-space: nowrap;',
            '}',
            '[data-sb] .sb-btn:hover { filter: brightness(1.15); transform: translateY(-1px); }',
            '[data-sb] .sb-btn:active { transform: translateY(0); }',
            '[data-sb] .sb-btn-blue { background: #4a6cf7; color: #fff; }',
            '[data-sb] .sb-btn-green { background: #2ecc71; color: #fff; }',
            '[data-sb] .sb-btn-red { background: #e74c3c; color: #fff; }',
            '[data-sb] .sb-btn-yellow { background: #f1c40f; color: #1a1a2e; }',
            '[data-sb] .sb-btn-ghost { background: rgba(255,255,255,0.06); color: #ccc; border: 1px solid rgba(255,255,255,0.1); }',
            '[data-sb] .sb-btn-ghost:hover { background: rgba(255,255,255,0.12); }',
            '[data-sb] .sb-btn-sm { padding: 7px 12px; font-size: 13px; }',
            '[data-sb] .sb-btn-icon { padding: 7px 10px; font-size: 16px; min-width: 32px; display: inline-flex; align-items: center; justify-content: center; }',

            // Step buttons
            '[data-sb] .sb-step-btn {',
            '  padding: 8px 14px; border: 1px solid rgba(255,255,255,0.08); border-radius: 8px;',
            '  cursor: pointer; font-size: 14px; font-weight: 600; min-width: 44px; text-align: center;',
            '  background: rgba(255,255,255,0.04); color: #aab; transition: all 0.15s;',
            '}',
            '[data-sb] .sb-step-btn:hover { background: rgba(108,138,255,0.15); border-color: rgba(108,138,255,0.3); }',
            '[data-sb] .sb-step-btn.active { background: #4a6cf7; color: #fff; border-color: #4a6cf7; box-shadow: 0 2px 8px rgba(74,108,247,0.3); }',

            // Labels
            '[data-sb] .sb-label { font-size: 14px; color: #7a7f8e; min-width: 65px; flex-shrink: 0; }',
            '[data-sb] .sb-row { display: flex; gap: 8px; align-items: center; margin-bottom: 10px; flex-wrap: wrap; }',

            // Section headers
            '[data-sb] .sb-section-header {',
            '  font-size: 14px; font-weight: 700; color: #6c8aff; cursor: pointer; padding: 12px 0 8px;',
            '  user-select: none; display: flex; align-items: center; gap: 6px; letter-spacing: 0.3px;',
            '  text-transform: uppercase;',
            '}',
            '[data-sb] .sb-section-header:hover { color: #8aa4ff; }',
            '[data-sb] .sb-section-divider { border-top: 1px solid rgba(255,255,255,0.06); margin-top: 4px; }',

            // Code block
            '[data-sb] .sb-code { font-family: "Fira Code", "Cascadia Code", "JetBrains Mono", monospace; }',
            '[data-sb] .sb-selector { color: #2ecc71; font-size: 12px; word-break: break-all; font-family: "Fira Code", monospace; }',

            // Scrollbar
            '[data-sb] ::-webkit-scrollbar { width: 6px; }',
            '[data-sb] ::-webkit-scrollbar-track { background: transparent; }',
            '[data-sb] ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }',
            '[data-sb] ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }',

            // Resize handle
            '[data-sb] .sb-resize-handle {',
            '  position: absolute; width: 12px; height: 12px; cursor: nwse-resize;',
            '  bottom: 0; right: 0; z-index: 2;',
            '}',
            '[data-sb] .sb-resize-handle::after {',
            '  content: ""; position: absolute; right: 3px; bottom: 3px;',
            '  width: 8px; height: 8px; border-right: 2px solid rgba(255,255,255,0.15);',
            '  border-bottom: 2px solid rgba(255,255,255,0.15);',
            '}',
            '[data-sb] .sb-resize-left {',
            '  position: absolute; width: 6px; top: 40px; bottom: 0; left: 0; cursor: ew-resize; z-index: 2;',
            '}',
            '[data-sb] .sb-resize-top {',
            '  position: absolute; height: 6px; top: 0; left: 0; right: 0; cursor: ns-resize; z-index: 2;',
            '}',

            // Option row
            '[data-sb] .sb-option-row {',
            '  display: flex; gap: 8px; align-items: center; padding: 10px 12px;',
            '  background: rgba(255,255,255,0.03); border-radius: 8px; margin-bottom: 4px;',
            '  border: 1px solid rgba(255,255,255,0.04);',
            '}',

            // Tooltip badge
            '[data-sb] .sb-badge {',
            '  display: inline-block; font-size: 11px; padding: 2px 6px; border-radius: 4px;',
            '  background: rgba(108,138,255,0.15); color: #6c8aff; font-weight: 600;',
            '}',
            // Responsive
            '@media (max-width: 600px) {',
            '  [data-sb] input, [data-sb] select, [data-sb] textarea { font-size: 14px; padding: 8px; }',
            '  [data-sb] .sb-btn { font-size: 13px; padding: 8px 12px; }',
            '  [data-sb] .sb-btn-sm { font-size: 12px; padding: 6px 10px; }',
            '  [data-sb] .sb-step-btn { font-size: 12px; padding: 6px 10px; }',
            '  [data-sb] .sb-row { gap: 6px; margin-bottom: 8px; }',
            '  [data-sb] .sb-label { font-size: 12px; min-width: 50px; }',
            '  [data-sb] .sb-section-header { font-size: 12px; }',
            '}',
        ].join('\n');
        document.head.appendChild(styleEl);

        // =============================================
        // HELPERS
        // =============================================
        function el(tag, attrs, children) {
            var e = document.createElement(tag);
            if (attrs) {
                Object.keys(attrs).forEach(function(k) {
                    if (k === 'style' && typeof attrs[k] === 'object') {
                        Object.assign(e.style, attrs[k]);
                    } else if (k === 'className') {
                        e.className = attrs[k];
                    } else if (k.indexOf('on') === 0) {
                        e.addEventListener(k.slice(2).toLowerCase(), attrs[k]);
                    } else if (k === 'textContent') {
                        e.textContent = attrs[k];
                    } else if (k === 'innerHTML') {
                        // Dev-only: innerHTML is safe here since this tool is gated behind __TUTORIAL_DEV
                        e.innerHTML = attrs[k];
                    } else if (k === 'value') {
                        e.value = attrs[k];
                    } else if (k === 'checked') {
                        e.checked = attrs[k];
                    } else if (k === 'disabled') {
                        e.disabled = attrs[k];
                    } else if (k === 'readOnly') {
                        e.readOnly = attrs[k];
                    } else if (k === 'type') {
                        e.type = attrs[k];
                    } else if (k === 'placeholder') {
                        e.placeholder = attrs[k];
                    } else {
                        e.setAttribute(k, attrs[k]);
                    }
                });
            }
            if (children) {
                (Array.isArray(children) ? children : [children]).forEach(function(c) {
                    if (typeof c === 'string') e.appendChild(document.createTextNode(c));
                    else if (c) e.appendChild(c);
                });
            }
            return e;
        }

        function makeBtn(text, className, onClick) {
            return el('button', { textContent: text, className: 'sb-btn ' + className, onClick: onClick });
        }

        function makeInput(value, onChange, extra) {
            var inp = el('input', { type: 'text', value: value || '' });
            if (extra) Object.keys(extra).forEach(function(k) {
                if (k === 'style') Object.assign(inp.style, extra[k]);
                else inp[k] = extra[k];
            });
            inp.addEventListener('input', function() { onChange(inp.value); });
            return inp;
        }

        function makeNumberInput(value, onChange, extra) {
            var inp = el('input', { type: 'number', value: value });
            if (extra) Object.keys(extra).forEach(function(k) { inp[k] = extra[k]; });
            inp.addEventListener('input', function() { onChange(parseFloat(inp.value) || 0); });
            return inp;
        }

        function makeSelect(options, selected, onChange) {
            var sel = el('select');
            options.forEach(function(o) {
                var opt = el('option', { value: o.value, textContent: o.label || o.value });
                if (o.value === selected) opt.selected = true;
                sel.appendChild(opt);
            });
            sel.addEventListener('change', function() { onChange(sel.value); });
            return sel;
        }

        function makeCheckbox(label, checked, onChange) {
            var lbl = el('label', { style: { display: 'inline-flex', alignItems: 'center', gap: '5px', fontSize: '14px', color: '#b0b4c0', cursor: 'pointer' } });
            var cb = el('input', { type: 'checkbox', checked: !!checked });
            cb.addEventListener('change', function() { onChange(cb.checked); });
            lbl.appendChild(cb);
            lbl.appendChild(document.createTextNode(label));
            return lbl;
        }

        function makeRow(children) {
            var r = el('div', { className: 'sb-row' });
            children.forEach(function(c) { if (c) r.appendChild(c); });
            return r;
        }

        function makeLabeledRow(labelText, control) {
            var r = el('div', { className: 'sb-row' });
            r.appendChild(el('label', { textContent: labelText, className: 'sb-label' }));
            if (typeof control === 'string') {
                r.appendChild(el('span', { textContent: control, className: 'sb-selector' }));
            } else {
                control.style.flex = '1';
                r.appendChild(control);
            }
            return r;
        }


        function ensureCursor(s) {
            if (!s.cursor) s.cursor = {};
            return s.cursor;
        }

        function ensureHighlight(s) {
            if (!s.highlight) s.highlight = { groups: [], padding: 8, borderRadius: 8, _mode: 'together' };
            return s.highlight;
        }

        function ensureScrollInside(s) {
            if (!s.scrollInside) s.scrollInside = {};
            return s.scrollInside;
        }

        function defaultSegment() {
            return { cp1: { x: 0.3, y: 0.1 }, cp2: { x: 0.7, y: 0.9 } };
        }

        function parseSvgControlPoints(svgPath) {
            var seg = defaultSegment();
            if (svgPath) {
                var m = svgPath.match(/C\s*([-\d.]+),([-\d.]+)\s+([-\d.]+),([-\d.]+)/);
                if (m) {
                    seg.cp1 = { x: parseFloat(m[1]), y: parseFloat(m[2]) };
                    seg.cp2 = { x: parseFloat(m[3]), y: parseFloat(m[4]) };
                }
            }
            return seg;
        }

        function ensureSegments(pe) {
            while (pe.segments.length < pe.points.length - 1) pe.segments.push(defaultSegment());
            while (pe.segments.length > pe.points.length - 1) pe.segments.pop();
        }

        // --- Collapsible section ---
        function makeSection(icon, title, contentFn) {
            var open = true;
            var wrapper = el('div', { className: 'sb-section-divider' });
            var content = el('div');
            var arrow = el('span', { textContent: '\u25BE', style: { fontSize: '10px', transition: 'transform 0.2s', display: 'inline-block' } });
            var header = el('div', { className: 'sb-section-header' }, [
                el('span', { textContent: icon }),
                el('span', { textContent: title }),
                arrow,
            ]);
            header.addEventListener('click', function() {
                open = !open;
                content.style.display = open ? 'block' : 'none';
                arrow.textContent = open ? '\u25BE' : '\u25B8';
            });
            wrapper.appendChild(header);
            wrapper.appendChild(content);
            return { wrapper: wrapper, content: content, refresh: function() { contentFn(content); } };
        }

        // --- Pick mechanism ---
        function pickElement(callback) {
            var savedPointerEvents = ctx.overlay.style.pointerEvents;
            var savedContainerPE = ctx.container.style.pointerEvents;
            var savedSvgDisplay = svgOverlay.style.display;
            // Let clicks pass through the overlay backdrop, but keep container (avatar/bubble) selectable
            ctx.overlay.style.pointerEvents = 'none';
            ctx.container.style.pointerEvents = 'auto';
            panel.style.pointerEvents = 'none';
            svgOverlay.style.display = 'none';
            // Hide drag handles during pick
            peHandles.forEach(function(h) { h.h1.style.display = 'none'; h.h2.style.display = 'none'; });
            pePointDots.forEach(function(d) { if (d.style) d.style.display = 'none'; });
            document.body.style.cursor = 'crosshair';

            var hoverOutline = el('div', { style: {
                position: 'fixed', border: '2px solid #6c8aff', borderRadius: '4px', pointerEvents: 'none',
                zIndex: '100001', display: 'none', transition: 'all 0.08s ease',
                boxShadow: '0 0 0 3px rgba(108,138,255,0.2)',
            } });
            var hoverLabel = el('div', { style: {
                position: 'fixed', background: '#1a1a2e', color: '#6c8aff', padding: '2px 6px',
                borderRadius: '4px', fontSize: '10px', fontFamily: 'monospace', pointerEvents: 'none',
                zIndex: '100002', display: 'none', whiteSpace: 'nowrap',
            } });
            document.body.appendChild(hoverOutline);
            document.body.appendChild(hoverLabel);

            function getElementUnder(x, y) {
                hoverOutline.style.display = 'none';
                hoverLabel.style.display = 'none';
                var found = document.elementFromPoint(x, y);
                // If we hit the overlay backdrop itself (not its children like avatar/bubble), peek through
                if (found && found === ctx.overlay) {
                    ctx.overlay.style.display = 'none';
                    found = document.elementFromPoint(x, y);
                    ctx.overlay.style.display = '';
                }
                hoverOutline.style.display = '';
                hoverLabel.style.display = '';
                return found;
            }

            function onMove(e) {
                var target = getElementUnder(e.clientX, e.clientY);
                if (target && target !== hoverOutline && target !== hoverLabel && !panel.contains(target)) {
                    var rect = target.getBoundingClientRect();
                    Object.assign(hoverOutline.style, {
                        display: 'block',
                        left: rect.left + 'px', top: rect.top + 'px',
                        width: rect.width + 'px', height: rect.height + 'px',
                    });
                    var sel = TutorialEngine.bestSelector(target);
                    hoverLabel.textContent = sel;
                    hoverLabel.style.display = 'block';
                    hoverLabel.style.left = (rect.left) + 'px';
                    hoverLabel.style.top = (rect.top - 22) + 'px';
                }
            }

            function cleanup() {
                document.removeEventListener('mousemove', onMove, true);
                document.removeEventListener('click', onClick, true);
                document.removeEventListener('keydown', onEsc, true);
                if (hoverOutline.parentNode) hoverOutline.parentNode.removeChild(hoverOutline);
                if (hoverLabel.parentNode) hoverLabel.parentNode.removeChild(hoverLabel);
                document.body.style.cursor = '';
                ctx.overlay.style.pointerEvents = savedPointerEvents;
                ctx.container.style.pointerEvents = savedContainerPE;
                panel.style.pointerEvents = 'auto';
                svgOverlay.style.display = savedSvgDisplay;
                // Restore drag handles
                peHandles.forEach(function(h) { h.h1.style.display = 'block'; h.h2.style.display = 'block'; });
                pePointDots.forEach(function(d) { if (d.style) d.style.display = ''; });
            }

            function onClick(e) {
                e.preventDefault();
                e.stopPropagation();
                var target = getElementUnder(e.clientX, e.clientY);
                cleanup();
                if (target && !panel.contains(target)) {
                    var selector = TutorialEngine.bestSelector(target);
                    var rect = target.getBoundingClientRect();
                    var anchorX = (e.clientX - rect.left) / rect.width;
                    var anchorY = (e.clientY - rect.top) / rect.height;
                    callback(selector, { x: Math.round(anchorX * 100) / 100, y: Math.round(anchorY * 100) / 100 }, target);
                }
            }

            function onEsc(e) {
                if (e.key === 'Escape') cleanup();
            }

            document.addEventListener('mousemove', onMove, true);
            document.addEventListener('click', onClick, true);
            document.addEventListener('keydown', onEsc, true);
            // Touch support: tap to pick (no hover feedback on touch)
            document.addEventListener('touchend', function onTouch(e) {
                var touch = e.changedTouches[0];
                onClick({ clientX: touch.clientX, clientY: touch.clientY, preventDefault: function(){}, stopPropagation: function(){} });
                document.removeEventListener('touchend', onTouch, true);
            }, true);
        }

        // --- Page info ---
        var pagePath = window.location.pathname;

        // --- Diff helper: list changed properties between original and current step ---
        function normalizeHighlight(hl) {
            if (!hl || !hl.groups) return hl;
            // Extract only the comparable data: groups as sorted arrays of selectors + padding/borderRadius
            var groups = hl.groups.map(function(g) {
                var elems = g.elements || g;
                if (!Array.isArray(elems)) return [];
                return elems.slice().sort();
            });
            return { groups: groups, padding: hl.padding, borderRadius: hl.borderRadius };
        }

        function getStepChanges(index) {
            var orig = originalSteps[index];
            var curr = peSteps[index];
            if (!orig || !curr) return ['new step'];
            var changes = [];
            var allKeys = {};
            Object.keys(orig).forEach(function(k) { allKeys[k] = true; });
            Object.keys(curr).forEach(function(k) { allKeys[k] = true; });
            Object.keys(allKeys).forEach(function(k) {
                if (k === '_branch' || k === '_pe' || k === 'advance' || k === 'autoNextDelay') return;
                var a = orig[k];
                var b = curr[k];
                // Treat empty array same as undefined/null
                var aEmpty = a === undefined || a === null || (Array.isArray(a) && a.length === 0);
                var bEmpty = b === undefined || b === null || (Array.isArray(b) && b.length === 0);
                if (aEmpty && bEmpty) return;
                // Normalize highlights before comparing
                if (k === 'highlight') {
                    if (JSON.stringify(normalizeHighlight(a)) !== JSON.stringify(normalizeHighlight(b))) changes.push(k);
                } else {
                    if (JSON.stringify(a) !== JSON.stringify(b)) changes.push(k);
                }
            });
            return changes;
        }

        // --- Current step helper ---
        function cur() { return peSteps[selectedIndex] || {}; }
        function save(refreshLive) {
            // Sync peSteps data into the live tutorial steps (without re-rendering)
            syncToLiveSteps();
            onStepChanged();
            // Only re-render live when explicitly requested (layout changes)
            if (refreshLive) ctx.showStep(selectedIndex);
        }

        function saveRefresh(section, refreshLive) {
            save(refreshLive);
            if (section) section.refresh();
        }

        function syncToLiveSteps() {
            while (ctx.steps.length > peSteps.length) ctx.steps.pop();
            peSteps.forEach(function(pe, i) {
                if (!ctx.steps[i]) ctx.steps[i] = {};
                var live = ctx.steps[i];
                live.html = pe.html;
                live.overlay = pe.overlay;
                live.position = pe.position;
                live.align = pe.align;
                live.cursor = pe.cursor ? JSON.parse(JSON.stringify(pe.cursor)) : null;
                live.highlight = pe.highlight ? JSON.parse(JSON.stringify(pe.highlight)) : null;
                live.scrollTo = pe.scrollTo || null;
                live.scrollInside = pe.scrollInside ? JSON.parse(JSON.stringify(pe.scrollInside)) : null;
                live.options = pe.options && pe.options.length ? JSON.parse(JSON.stringify(pe.options)) : null;
                live.redirect = pe.redirect || null;
                live.showBack = pe.showBack !== undefined && pe.showBack !== false ? pe.showBack : undefined;
                live.silentClick = pe.silentClick || undefined;
                live.hover = pe.hover || undefined;
                live.chatMode = pe.chatMode || undefined;
                live.chatMaxWidth = pe.chatMaxWidth || undefined;
                live.positionTarget = pe.positionTarget || undefined;
                live.showIf = pe.showIf || undefined;
                live.clearOverlay = pe.clearOverlay || undefined;
                delete live.autoNext;
                delete live.afterAnimation;
                if (pe.advance === 'auto') live.autoNext = pe.autoNextDelay || 3;
                else if (pe.advance === 'afterAnimation') live.afterAnimation = true;
            });
        }

        // =============================================
        // PANEL
        // =============================================
        var panel = el('div', {
            id: 'tutorial-step-builder',
            'data-sb': '',
            style: {
                position: 'fixed', bottom: '12px', right: '12px', zIndex: '100000',
                background: 'linear-gradient(170deg, #1e1e32 0%, #161625 100%)',
                color: '#e0e4ec', borderRadius: '14px',
                padding: '0', width: 'clamp(320px, 90vw, 480px)', minWidth: '280px', maxWidth: '95vw', minHeight: '200px',
                fontSize: '14px',
                boxShadow: '0 12px 48px rgba(0,0,0,0.5), 0 2px 12px rgba(0,0,0,0.3), inset 0 1px 0 rgba(255,255,255,0.05)',
                border: '1px solid rgba(255,255,255,0.06)',
                maxHeight: '85vh', display: 'flex', flexDirection: 'column',
                overflow: 'hidden',
            },
        });

        // --- Resize handles ---
        var resizeHandle = el('div', { className: 'sb-resize-handle' });
        var resizeLeft = el('div', { className: 'sb-resize-left' });
        var resizeTop = el('div', { className: 'sb-resize-top' });
        panel.appendChild(resizeHandle);
        panel.appendChild(resizeLeft);
        panel.appendChild(resizeTop);

        function setupResize(handle, edges) {
            handle.addEventListener('mousedown', function(e) {
                e.preventDefault();
                var startX = e.clientX, startY = e.clientY;
                var startW = panel.offsetWidth, startH = panel.offsetHeight;
                var startLeft = panel.getBoundingClientRect().left;
                var startTop = panel.getBoundingClientRect().top;

                function onMove(e) {
                    if (edges.right) {
                        var w = Math.max(300, startW + (e.clientX - startX));
                        panel.style.width = w + 'px';
                    }
                    if (edges.bottom) {
                        var h = Math.max(200, startH + (e.clientY - startY));
                        panel.style.maxHeight = h + 'px';
                    }
                    if (edges.left) {
                        var dx = e.clientX - startX;
                        var w = Math.max(300, startW - dx);
                        panel.style.width = w + 'px';
                        panel.style.left = (startLeft + startW - w) + 'px';
                        panel.style.right = 'auto';
                    }
                    if (edges.top) {
                        var dy = e.clientY - startY;
                        var h = Math.max(200, startH - dy);
                        panel.style.maxHeight = h + 'px';
                        panel.style.top = (startTop + startH - h) + 'px';
                        panel.style.bottom = 'auto';
                    }
                }
                function onUp() {
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                }
                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            });
            // Touch support for resize
            handle.addEventListener('touchstart', function(e) {
                e.preventDefault();
                var startX = e.touches[0].clientX, startY = e.touches[0].clientY;
                var startW = panel.offsetWidth, startH = panel.offsetHeight;
                var startLeft = panel.getBoundingClientRect().left;
                var startTop = panel.getBoundingClientRect().top;
                function onTouchMove(e) {
                    var te = { clientX: e.touches[0].clientX, clientY: e.touches[0].clientY };
                    if (edges.right) {
                        var w = Math.max(300, startW + (te.clientX - startX));
                        panel.style.width = w + 'px';
                    }
                    if (edges.bottom) {
                        var h = Math.max(200, startH + (te.clientY - startY));
                        panel.style.maxHeight = h + 'px';
                    }
                    if (edges.left) {
                        var dx = te.clientX - startX;
                        var w = Math.max(300, startW - dx);
                        panel.style.width = w + 'px';
                        panel.style.left = (startLeft + startW - w) + 'px';
                        panel.style.right = 'auto';
                    }
                    if (edges.top) {
                        var dy = te.clientY - startY;
                        var h = Math.max(200, startH - dy);
                        panel.style.maxHeight = h + 'px';
                        panel.style.top = (startTop + startH - h) + 'px';
                        panel.style.bottom = 'auto';
                    }
                }
                function onTouchEnd() {
                    document.removeEventListener('touchmove', onTouchMove);
                    document.removeEventListener('touchend', onTouchEnd);
                }
                document.addEventListener('touchmove', onTouchMove);
                document.addEventListener('touchend', onTouchEnd);
            });
        }
        setupResize(resizeHandle, { right: true, bottom: true });
        setupResize(resizeLeft, { left: true });
        setupResize(resizeTop, { top: true });

        // =============================================
        // HEADER
        // =============================================
        var collapseBtn = el('button', {
            textContent: '\u25BE',
            className: 'sb-btn sb-btn-ghost sb-btn-icon',
            style: { fontSize: '14px', transition: 'transform 0.2s' },
            onClick: function() {
                var isCollapsed = body.style.display === 'none';
                body.style.display = isCollapsed ? 'block' : 'none';
                collapseBtn.textContent = isCollapsed ? '\u25BE' : '\u25B8';
            },
        });

        var headerEl = el('div', {
            style: {
                padding: '10px 14px', display: 'flex', alignItems: 'center', gap: '8px',
                cursor: 'grab', borderBottom: '1px solid rgba(255,255,255,0.06)', flexShrink: '0',
                userSelect: 'none', background: 'rgba(255,255,255,0.02)',
                borderRadius: '14px 14px 0 0',
            },
        }, [
            el('span', { textContent: '\u2630', style: { fontSize: '14px', color: '#6c8aff', opacity: '0.6' } }),
            el('span', { textContent: 'Step Builder', style: { fontSize: '16px', fontWeight: '700', color: '#e0e4ec', flex: '1' } }),
            (function() {
                var devLabel = el('label', { style: { display: 'flex', alignItems: 'center', gap: '4px', fontSize: '10px', color: '#7a7f8e', cursor: 'pointer' } });
                var devCb = el('input', { type: 'checkbox', checked: !!window._tutorialDevMode });
                devCb.addEventListener('change', function() { window._tutorialDevMode = devCb.checked; });
                devLabel.appendChild(devCb);
                devLabel.appendChild(el('span', { textContent: 'Dev' }));
                return devLabel;
            })(),
            collapseBtn,
        ]);
        panel.appendChild(headerEl);

        // Draggable
        (function() {
            var dragging = false, offX = 0, offY = 0;
            headerEl.addEventListener('mousedown', function(e) {
                if (e.target.tagName === 'BUTTON') return;
                dragging = true;
                offX = e.clientX - panel.getBoundingClientRect().left;
                offY = e.clientY - panel.getBoundingClientRect().top;
                headerEl.style.cursor = 'grabbing';
            });
            document.addEventListener('mousemove', function(e) {
                if (!dragging) return;
                panel.style.left = (e.clientX - offX) + 'px';
                panel.style.top = (e.clientY - offY) + 'px';
                panel.style.right = 'auto';
                panel.style.bottom = 'auto';
            });
            document.addEventListener('mouseup', function() {
                dragging = false;
                headerEl.style.cursor = 'grab';
            });
            // Touch support for dragging
            headerEl.addEventListener('touchstart', function(e) {
                if (e.target.tagName === 'BUTTON') return;
                var touch = e.touches[0];
                dragging = true;
                offX = touch.clientX - panel.getBoundingClientRect().left;
                offY = touch.clientY - panel.getBoundingClientRect().top;
            }, { passive: true });
            document.addEventListener('touchmove', function(e) {
                if (!dragging) return;
                var touch = e.touches[0];
                panel.style.left = (touch.clientX - offX) + 'px';
                panel.style.top = (touch.clientY - offY) + 'px';
                panel.style.right = 'auto';
                panel.style.bottom = 'auto';
            }, { passive: true });
            document.addEventListener('touchend', function() {
                dragging = false;
            });
        })();

        // Scrollable body
        var body = el('div', { style: { padding: '14px 16px', overflowY: 'auto', flex: '1' } });
        panel.appendChild(body);

        // =============================================
        // 1. STEP LIST BAR
        // =============================================
        var stepListBar = el('div', { style: { marginBottom: '10px', paddingBottom: '10px', borderBottom: '1px solid rgba(255,255,255,0.06)' } });
        var stepListContainer = el('div', { style: { display: 'flex', flexWrap: 'wrap', gap: '4px', marginBottom: '8px' } });
        var stepListActions = el('div', { style: { display: 'flex', gap: '6px' } });

        stepListBar.appendChild(stepListContainer);
        stepListBar.appendChild(stepListActions);
        body.appendChild(stepListBar);
        // Global opts (created here, populated later at line ~807)
        var globalOptsDiv = el('div', { style: { padding: '6px 0', borderBottom: '1px solid rgba(255,255,255,0.06)', marginBottom: '6px' } });
        body.appendChild(globalOptsDiv);

        function getStepIcons(s) {
            var icons = '';
            if (s.cursor) icons += '\uD83C\uDFAF';
            if (s.highlight) icons += '\uD83D\uDD26';
            if (s.options && s.options.length) icons += '\uD83D\uDDC2\uFE0F';
            if (s.advance === 'auto') icons += '\u23ED\uFE0F';
            return icons;
        }

        function renderStepList() {
            stepListContainer.innerHTML = '';
            var lastBranch = null;
            peSteps.forEach(function(s, i) {
                // Add branch separator when _branch changes
                if (s._branch && s._branch !== lastBranch) {
                    lastBranch = s._branch;
                    var separator = el('div', {
                        textContent: s._branch,
                        style: {
                            width: '100%',
                            fontSize: '10px',
                            fontWeight: '600',
                            color: '#7a7f8e',
                            textTransform: 'uppercase',
                            letterSpacing: '0.5px',
                            padding: '6px 0 2px 0',
                            borderTop: lastBranch !== null && i > 0 ? '1px solid rgba(255,255,255,0.08)' : 'none',
                            marginTop: i > 0 ? '4px' : '0',
                        }
                    });
                    stepListContainer.appendChild(separator);
                }
                var icons = getStepIcons(s);
                var btn = el('button', {
                    textContent: i + (icons ? ' ' + icons : ''),
                    className: 'sb-step-btn' + (i === selectedIndex ? ' active' : ''),
                    onClick: (function(idx) { return function() { selectStep(idx); }; })(i),
                });
                stepListContainer.appendChild(btn);
            });
        }

        // Copy All / Copy Step / Spectator
        stepListActions.appendChild(makeBtn('\uD83D\uDCCB Copy All', 'sb-btn-green', function() {
            var allChanges = [];
            peSteps.forEach(function(s, i) {
                var changes = getStepChanges(i);
                if (changes.length) allChanges.push('Step ' + i + ': ' + changes.join(', '));
            });
            var parts = [];
            parts.push('/tutorial-creator');
            parts.push('');
            parts.push('Page: ' + pagePath);
            if (allChanges.length) {
                parts.push('Changes:');
                allChanges.forEach(function(c) { parts.push('  - ' + c); });
            }
            parts.push('');
            parts.push(generateFullCode());
            copyToClipboard(parts.join('\n'));
        }));
        stepListActions.appendChild(makeBtn('Copy Step', 'sb-btn-ghost sb-btn-sm', function() {
            var s = cur();
            var branch = s._branch || '';
            var stepCode = generateStepCode(s);
            var changes = getStepChanges(selectedIndex);
            var parts = [];
            parts.push('/tutorial-creator');
            parts.push('');
            parts.push('Page: ' + pagePath);
            parts.push('Step ' + selectedIndex + (branch ? ' (branch: ' + branch + ')' : ''));
            if (changes.length) {
                parts.push('Changed: ' + changes.join(', '));
            }
            parts.push('');
            parts.push(stepCode);
            copyToClipboard(parts.join('\n'));
        }));
        stepListActions.appendChild(makeBtn('\uD83D\uDC41 Spectator', 'sb-btn-blue sb-btn-sm', function() {
            enterSpectatorMode();
        }));
        stepListActions.appendChild(makeBtn('Copy Changed', 'sb-btn-ghost sb-btn-sm', function() {
            var changedSteps = [];
            peSteps.forEach(function(s, i) {
                var changes = getStepChanges(i);
                if (changes.length) changedSteps.push(i);
            });
            if (!changedSteps.length) { copyToClipboard('No changes'); return; }
            var parts = [];
            parts.push('/tutorial-creator');
            parts.push('');
            parts.push('Page: ' + pagePath);
            parts.push('Changed steps: ' + changedSteps.join(', '));
            parts.push('');
            changedSteps.forEach(function(i) {
                var s = peSteps[i];
                var branch = s._branch || '';
                var changes = getStepChanges(i);
                parts.push('Step ' + i + (branch ? ' (branch: ' + branch + ')' : '') + ' — Changed: ' + changes.join(', '));
                parts.push(generateStepCode(s));
                parts.push('');
            });
            copyToClipboard(parts.join('\n'));
        }));
        stepListActions.appendChild(makeBtn('+ Step', 'sb-btn-green sb-btn-sm', function() {
            var newStep = { html: '', overlay: true, position: 'left', align: 'center', advance: 'click', options: [] };
            peSteps.splice(selectedIndex + 1, 0, newStep);
            selectStep(selectedIndex + 1);
        }));

        // =============================================
        // GLOBAL TUTORIAL OPTIONS (width + font size)
        // =============================================
        var goWidthInput = makeInput(ctx.opts.bubbleMaxWidth, function(v) {
            ctx.opts.bubbleMaxWidth = v;
            ctx.opts.bubbleMinWidth = v;
            ctx.showStep(selectedIndex);
        });
        goWidthInput.style.flex = '1';
        goWidthInput.style.fontSize = '10px';
        var goFontInput = makeInput(ctx.opts.bubbleFontSize, function(v) {
            ctx.opts.bubbleFontSize = v;
            ctx.showStep(selectedIndex);
        });
        goFontInput.style.flex = '1';
        goFontInput.style.fontSize = '10px';
        globalOptsDiv.appendChild(makeRow([
            el('label', { textContent: 'width', className: 'sb-label', style: { minWidth: 'auto' } }),
            goWidthInput,
            el('label', { textContent: 'font', className: 'sb-label', style: { minWidth: 'auto', marginLeft: '8px' } }),
            goFontInput,
        ]));

        // =============================================
        // 2. STEP DETAIL HEADER
        // =============================================
        var stepDetailHeader = el('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '10px' } });
        var stepDetailLabel = el('span', { style: { fontSize: '16px', fontWeight: '700', color: '#e0e4ec' } });
        var stepDetailActions = el('div', { style: { display: 'flex', gap: '3px' } });

        stepDetailActions.appendChild(makeBtn('\u2191', 'sb-btn-ghost sb-btn-icon', function() {
            if (selectedIndex > 0) {
                var tmp = peSteps[selectedIndex];
                peSteps[selectedIndex] = peSteps[selectedIndex - 1];
                peSteps[selectedIndex - 1] = tmp;
                selectStep(selectedIndex - 1);
            }
        }));
        stepDetailActions.appendChild(makeBtn('\u2193', 'sb-btn-ghost sb-btn-icon', function() {
            if (selectedIndex < peSteps.length - 1) {
                var tmp = peSteps[selectedIndex];
                peSteps[selectedIndex] = peSteps[selectedIndex + 1];
                peSteps[selectedIndex + 1] = tmp;
                selectStep(selectedIndex + 1);
            }
        }));
        stepDetailActions.appendChild(makeBtn('\u2715', 'sb-btn-red sb-btn-icon', function() {
            if (peSteps.length <= 1) return;
            peSteps.splice(selectedIndex, 1);
            selectStep(Math.min(selectedIndex, peSteps.length - 1));
        }));

        stepDetailHeader.appendChild(stepDetailLabel);
        stepDetailHeader.appendChild(stepDetailActions);
        body.appendChild(stepDetailHeader);

        // =============================================
        // 3. BASE CONFIG
        // =============================================
        var baseConfigDiv = el('div');
        body.appendChild(baseConfigDiv);

        function renderBaseConfig() {
            baseConfigDiv.innerHTML = '';
            var s = cur();

            // html
            baseConfigDiv.appendChild(makeLabeledRow('html', makeInput(s.html || '', function(v) { s.html = v; save(true); }, { placeholder: 'tutorial.section.key' })));

            // Row: overlay + position + align
            var overlayCheck = makeCheckbox('overlay', s.overlay !== false, function(v) { s.overlay = v; save(true); });
            var clearOverlayCheck = makeCheckbox('block', !!s.clearOverlay, function(v) { s.clearOverlay = v || undefined; save(true); });
            var backVal = s.showBack === true ? 'prev' : (typeof s.showBack === 'number' ? 'goTo' : 'off');
            var backGoToInput = null;
            var backSelect = makeSelect(
                [{ value: 'off', label: 'Back: off' }, { value: 'prev', label: 'Back: prev' }, { value: 'goTo', label: 'Back: go to' }],
                backVal,
                function(v) {
                    if (v === 'off') s.showBack = undefined;
                    else if (v === 'prev') s.showBack = true;
                    else { s.showBack = 0; }
                    save(true);
                    renderBaseConfig();
                }
            );
            backSelect.style.width = '100px';
            if (backVal === 'goTo') {
                backGoToInput = makeNumberInput(s.showBack, function(v) { s.showBack = v; save(true); }, { min: '0', step: '1' });
                backGoToInput.style.width = '50px';
            }
            var posSelect = makeSelect([{ value: 'left' }, { value: 'right' }, { value: 'top' }, { value: 'bottom' }], s.position || 'left', function(v) { s.position = v; save(true); });
            posSelect.style.width = '75px';
            var alignSelect = makeSelect([{ value: 'left' }, { value: 'center' }, { value: 'right' }], s.align || 'center', function(v) { s.align = v; save(true); });
            alignSelect.style.width = '75px';

            var backRow = [overlayCheck, clearOverlayCheck, backSelect];
            if (backGoToInput) backRow.push(backGoToInput);
            backRow.push(el('label', { textContent: 'pos', className: 'sb-label', style: { minWidth: 'auto', marginLeft: '8px' } }));
            backRow.push(posSelect);
            backRow.push(el('label', { textContent: 'align', className: 'sb-label', style: { minWidth: 'auto', marginLeft: '4px' } }));
            backRow.push(alignSelect);
            baseConfigDiv.appendChild(makeRow(backRow));

            // advance
            var advanceSelect = makeSelect(
                [{ value: 'click', label: 'Click' }, { value: 'auto', label: 'Auto' }, { value: 'afterAnimation', label: 'After Anim' }],
                s.advance || 'click',
                function(v) {
                    s.advance = v;
                    if (v === 'auto' && !s.autoNextDelay) s.autoNextDelay = 3;
                    save();
                    renderBaseConfig();
                }
            );
            if (s.options && s.options.length) {
                advanceSelect.disabled = true;
                advanceSelect.style.opacity = '0.4';
            }
            advanceSelect.style.width = '110px';

            var advanceRow = [
                el('label', { textContent: 'advance', className: 'sb-label' }),
                advanceSelect,
            ];
            if (s.advance === 'auto') {
                var delayInput = makeNumberInput(s.autoNextDelay || 3, function(v) { s.autoNextDelay = v; save(); }, { step: '0.5', min: '0.5' });
                advanceRow.push(el('label', { textContent: 'delay', className: 'sb-label', style: { minWidth: 'auto', marginLeft: '6px' } }));
                advanceRow.push(delayInput);
                advanceRow.push(el('span', { textContent: 's', style: { color: '#7a7f8e', fontSize: '10px' } }));
            }
            var chatModeCheck = makeCheckbox('chat', !!s.chatMode, function(v) { s.chatMode = v || undefined; if (!v) s.chatMaxWidth = undefined; save(true); renderBaseConfig(); });
            advanceRow.push(chatModeCheck);
            if (s.chatMode) {
                var chatMaxWInput = makeInput(s.chatMaxWidth || '', function(v) { s.chatMaxWidth = v || undefined; save(true); }, { placeholder: 'maxW' });
                chatMaxWInput.style.width = '80px';
                chatMaxWInput.style.fontSize = '10px';
                advanceRow.push(chatMaxWInput);
            }
            baseConfigDiv.appendChild(makeRow(advanceRow));

            // silentClick
            var scDisplay = el('span', { textContent: s.silentClick || '\u2014', className: 'sb-selector', style: { flex: '1' } });
            baseConfigDiv.appendChild(makeRow([
                el('label', { textContent: 'silentClick', className: 'sb-label', style: { minWidth: 'auto' } }),
                scDisplay,
                makeBtn('Pick', 'sb-btn-yellow sb-btn-sm', function() {
                    pickElement(function(sel) {
                        s.silentClick = sel;
                        save();
                        renderBaseConfig();
                    });
                }),
                s.silentClick ? makeBtn('\u2715', 'sb-btn-ghost sb-btn-icon sb-btn-sm', function() {
                    delete s.silentClick;
                    save();
                    renderBaseConfig();
                }) : null,
            ]));

            // positionTarget
            var ptDisplay = el('span', { textContent: s.positionTarget || '\u2014', className: 'sb-selector', style: { flex: '1' } });
            baseConfigDiv.appendChild(makeRow([
                el('label', { textContent: 'target', className: 'sb-label', style: { minWidth: 'auto' } }),
                ptDisplay,
                makeBtn('Pick', 'sb-btn-green sb-btn-sm', function() {
                    pickElement(function(sel) {
                        s.positionTarget = sel;
                        save(true);
                        renderBaseConfig();
                    });
                }),
                s.positionTarget ? makeBtn('\u2715', 'sb-btn-ghost sb-btn-icon sb-btn-sm', function() {
                    delete s.positionTarget;
                    save(true);
                    renderBaseConfig();
                }) : null,
            ]));

            // showIf
            var siDisplay = el('span', { textContent: s.showIf || '\u2014', className: 'sb-selector', style: { flex: '1' } });
            baseConfigDiv.appendChild(makeRow([
                el('label', { textContent: 'showIf', className: 'sb-label', style: { minWidth: 'auto' } }),
                siDisplay,
                makeBtn('Pick', 'sb-btn-blue sb-btn-sm', function() {
                    pickElement(function(sel) {
                        s.showIf = sel;
                        save(true);
                        renderBaseConfig();
                    });
                }),
                s.showIf ? makeBtn('\u2715', 'sb-btn-ghost sb-btn-icon sb-btn-sm', function() {
                    delete s.showIf;
                    save(true);
                    renderBaseConfig();
                }) : null,
            ]));
        }

        // =============================================
        // 4. CURSOR SECTION (with SVG path editor)
        // =============================================
        var cursorSection = makeSection('\uD83C\uDFAF', 'Cursor', renderCursorContent);
        body.appendChild(cursorSection.wrapper);

        // --- SVG overlay for path visualization ---
        var svgOverlay = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        Object.assign(svgOverlay.style, { position: 'fixed', inset: '0', zIndex: '99998', pointerEvents: 'none', display: 'none' });
        svgOverlay.setAttribute('width', '100%'); svgOverlay.setAttribute('height', '100%');
        document.body.appendChild(svgOverlay);

        var createSvgEl = TutorialEngine.createSvgEl;

        // --- Path editor state (per-step, stored on peSteps[i]._pe) ---
        var peHandles = [];  // current SVG handle DOMs
        var pePointDots = [];
        var peSegPaths = [];
        var peCpLines = [];
        var peDragListeners = []; // stored for cleanup

        function getPe() {
            var s = cur();
            if (!s._pe) s._pe = { points: [], segments: [], actions: [], pauses: [] };
            return s._pe;
        }

        function clearPathOverlay() {
            peSegPaths.forEach(function(p) { if (p.parentNode) p.parentNode.removeChild(p); });
            peCpLines.forEach(function(l) { if (l.parentNode) l.parentNode.removeChild(l); });
            pePointDots.forEach(function(d) { if (d && d.parentNode) d.parentNode.removeChild(d); });
            peHandles.forEach(function(h) {
                if (h.h1 && h.h1.parentNode) h.h1.parentNode.removeChild(h.h1);
                if (h.h2 && h.h2.parentNode) h.h2.parentNode.removeChild(h.h2);
            });
            peSegPaths = []; peCpLines = []; pePointDots = []; peHandles = [];
            // Remove accumulated drag listeners
            peDragListeners.forEach(function(l) { document.removeEventListener(l.type, l.fn); });
            peDragListeners = [];
            svgOverlay.innerHTML = '';
            svgOverlay.style.display = 'none';
        }

        function createDragHandle(color) {
            var h = document.createElement('div');
            Object.assign(h.style, {
                position: 'fixed', width: '12px', height: '12px', borderRadius: '50%',
                backgroundColor: color, border: '2px solid #fff', cursor: 'grab',
                zIndex: '99999', display: 'none', transform: 'translate(-50%, -50%)',
            });
            document.body.appendChild(h);
            return h;
        }

        function loadPeFromCursor() {
            // Initialize _pe from cursor config (load existing path into editor)
            var s = cur();
            var c = s.cursor;
            var pe = getPe();
            pe.points = []; pe.segments = []; pe.actions = []; pe.pauses = [];
            if (!c || !c.from) return;

            var fromPos = TutorialEngine.resolveElementCenter(c.from, c.fromAnchor, cur());
            if (!fromPos) return;
            pe.points.push(fromPos);
            pe.actions.push('path');
            pe.pauses.push(0);

            if (c.waypoints && c.waypoints.length) {
                for (var i = 0; i < c.waypoints.length; i++) {
                    var wp = c.waypoints[i];
                    var wpPos = TutorialEngine.resolveElementCenter(wp.target, null, cur());
                    if (!wpPos) continue;
                    pe.points.push(wpPos);
                    pe.actions.push(wp.action || 'path');
                    pe.pauses.push(wp.pause || 0);
                    pe.segments.push(parseSvgControlPoints(wp.svgPath));
                }
            } else if (c.to) {
                var toPos = TutorialEngine.resolveElementCenter(c.to, c.toAnchor, cur());
                if (!toPos) return;
                pe.points.push(toPos);
                pe.actions.push(c.click ? 'click' : 'path');
                pe.pauses.push(0);
                pe.segments.push(parseSvgControlPoints(c.svgPath));
            }
        }

        function refreshPathPositions() {
            // Recalculate endpoint positions from selectors (after scroll/resize)
            var s = cur();
            var c = s.cursor;
            var pe = getPe();
            if (!c || pe.points.length < 2) return;

            // Update From (first point)
            if (c.from) {
                var fromPos = TutorialEngine.resolveElementCenter(c.from, c.fromAnchor, cur());
                if (fromPos) pe.points[0] = fromPos;
            }

            // Update To (last point)
            var lastIdx = pe.points.length - 1;
            if (c.to) {
                var toPos = TutorialEngine.resolveElementCenter(c.to, c.toAnchor, cur());
                if (toPos) pe.points[lastIdx] = toPos;
            }

            // Update waypoint positions (screen: targets stay fixed, selector targets update)
            if (c.waypoints) {
                for (var i = 0; i < c.waypoints.length && (i + 1) < lastIdx; i++) {
                    var wp = c.waypoints[i];
                    if (wp.target && wp.target.indexOf('screen:') !== 0) {
                        var wpPos = TutorialEngine.resolveElementCenter(wp.target, null, cur());
                        if (wpPos) pe.points[i + 1] = wpPos;
                    }
                }
            }

            // Redraw without rebuilding DOM (just update positions)
            updatePathVisuals();
            // Update all point dot positions (all are div elements now)
            for (var j = 0; j < pePointDots.length; j++) {
                var dot = pePointDots[j];
                if (dot && dot.style && pe.points[j]) {
                    dot.style.left = pe.points[j].x + 'px';
                    dot.style.top = pe.points[j].y + 'px';
                }
            }
        }

        // Listen for scroll/resize to keep path overlay in sync
        var cleanupScrollResize = TutorialEngine.onScrollResize(function() {
            if (svgOverlay.style.display !== 'none') refreshPathPositions();
        });

        function rebuildPathOverlay() {
            clearPathOverlay();
            var pe = getPe();
            if (pe.points.length < 1) return;

            svgOverlay.style.display = 'block';

            if (pe.points.length === 1) {
                var dot = createSvgEl('circle', { r: '7', fill: '#4caf50', cx: pe.points[0].x, cy: pe.points[0].y });
                svgOverlay.appendChild(dot); pePointDots.push(dot);
                return;
            }

            ensureSegments(pe);

            // Create SVG paths and handles for each segment
            for (var i = 0; i < pe.segments.length; i++) {
                var pLine = createSvgEl('path', { fill: 'none', stroke: '#ffd700', 'stroke-width': '2.5', 'stroke-dasharray': '8,4' });
                svgOverlay.appendChild(pLine); peSegPaths.push(pLine);

                var l1 = createSvgEl('line', { stroke: '#4fc3f7', 'stroke-width': '1', 'stroke-dasharray': '4,3' });
                var l2 = createSvgEl('line', { stroke: '#4fc3f7', 'stroke-width': '1', 'stroke-dasharray': '4,3' });
                svgOverlay.appendChild(l1); svgOverlay.appendChild(l2);
                peCpLines.push(l1, l2);

                var h1 = createDragHandle('#ff9800');
                var h2 = createDragHandle('#e91e63');
                peHandles.push({ h1: h1, h2: h2 });
                makeCpDraggable(h1, i, 'cp1');
                makeCpDraggable(h2, i, 'cp2');
            }

            // Create point dots (all draggable, including From/To)
            for (var j = 0; j < pe.points.length; j++) {
                var isFirst = j === 0, isLast = j === pe.points.length - 1;
                var color = isFirst ? '#4caf50' : (isLast ? '#f44336' : '#4fc3f7');
                var size = (isFirst || isLast) ? '16px' : '14px';
                {
                    var wpDiv = document.createElement('div');
                    Object.assign(wpDiv.style, {
                        position: 'fixed', width: size, height: size, borderRadius: '50%',
                        backgroundColor: color, border: '2px solid #fff', cursor: 'grab',
                        zIndex: '99999', transform: 'translate(-50%, -50%)',
                        left: pe.points[j].x + 'px', top: pe.points[j].y + 'px',
                    });
                    document.body.appendChild(wpDiv);
                    pePointDots.push(wpDiv);
                    makeWpDraggable(wpDiv, j);
                }
            }

            updatePathVisuals();
        }

        function updatePathVisuals() {
            var pe = getPe();
            for (var i = 0; i < pe.segments.length; i++) {
                var f = pe.points[i], t = pe.points[i + 1], seg = pe.segments[i];
                var dx = t.x - f.x, dy = t.y - f.y;
                var c1x = f.x + seg.cp1.x * dx, c1y = f.y + seg.cp1.y * dy;
                var c2x = f.x + seg.cp2.x * dx, c2y = f.y + seg.cp2.y * dy;

                peSegPaths[i].setAttribute('d', 'M ' + f.x + ',' + f.y + ' C ' + c1x + ',' + c1y + ' ' + c2x + ',' + c2y + ' ' + t.x + ',' + t.y);

                var li = i * 2;
                peCpLines[li].setAttribute('x1', f.x); peCpLines[li].setAttribute('y1', f.y);
                peCpLines[li].setAttribute('x2', c1x); peCpLines[li].setAttribute('y2', c1y);
                peCpLines[li + 1].setAttribute('x1', t.x); peCpLines[li + 1].setAttribute('y1', t.y);
                peCpLines[li + 1].setAttribute('x2', c2x); peCpLines[li + 1].setAttribute('y2', c2y);

                peHandles[i].h1.style.display = 'block';
                peHandles[i].h2.style.display = 'block';
                peHandles[i].h1.style.left = c1x + 'px'; peHandles[i].h1.style.top = c1y + 'px';
                peHandles[i].h2.style.left = c2x + 'px'; peHandles[i].h2.style.top = c2y + 'px';
            }
            // Update svgPath in cursor config from current segments
            syncPeToCursor();
        }

        function makeCpDraggable(handle, segIdx, cpKey) {
            var dragging = false;
            handle.addEventListener('mousedown', function(e) { e.preventDefault(); e.stopPropagation(); dragging = true; handle.style.cursor = 'grabbing'; });
            var onMoveXY = function(clientX, clientY) {
                var pe = getPe();
                var f = pe.points[segIdx], t = pe.points[segIdx + 1];
                if (!f || !t) return;
                var dx = t.x - f.x, dy = t.y - f.y;
                if (dx === 0 && dy === 0) return;
                pe.segments[segIdx][cpKey].x = (clientX - f.x) / dx;
                pe.segments[segIdx][cpKey].y = (clientY - f.y) / dy;
                updatePathVisuals();
                save();
            };
            var onMove = function(e) {
                if (!dragging) return;
                onMoveXY(e.clientX, e.clientY);
            };
            var onUp = function() { if (dragging) { dragging = false; handle.style.cursor = 'grab'; } };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
            peDragListeners.push({ type: 'mousemove', fn: onMove }, { type: 'mouseup', fn: onUp });
            // Touch support
            handle.addEventListener('touchstart', function(e) { e.preventDefault(); e.stopPropagation(); dragging = true; handle.style.cursor = 'grabbing'; });
            var onTouchMove = function(e) { if (!dragging) return; onMoveXY(e.touches[0].clientX, e.touches[0].clientY); };
            var onTouchEnd = function() { if (dragging) { dragging = false; handle.style.cursor = 'grab'; } };
            document.addEventListener('touchmove', onTouchMove);
            document.addEventListener('touchend', onTouchEnd);
            peDragListeners.push({ type: 'touchmove', fn: onTouchMove }, { type: 'touchend', fn: onTouchEnd });
        }

        function makeWpDraggable(wpDiv, pointIdx) {
            var dragging = false;
            wpDiv.addEventListener('mousedown', function(e) { e.preventDefault(); e.stopPropagation(); dragging = true; wpDiv.style.cursor = 'grabbing'; });
            var onMoveXY = function(clientX, clientY) {
                var pe = getPe();
                pe.points[pointIdx] = { x: clientX, y: clientY };
                wpDiv.style.left = clientX + 'px'; wpDiv.style.top = clientY + 'px';
                updatePathVisuals();
            };
            var onMove = function(e) {
                if (!dragging) return;
                onMoveXY(e.clientX, e.clientY);
            };
            var onUp = function() {
                if (dragging) { dragging = false; wpDiv.style.cursor = 'grab'; save(); }
            };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
            peDragListeners.push({ type: 'mousemove', fn: onMove }, { type: 'mouseup', fn: onUp });
            // Touch support
            wpDiv.addEventListener('touchstart', function(e) { e.preventDefault(); e.stopPropagation(); dragging = true; wpDiv.style.cursor = 'grabbing'; });
            var onTouchMove = function(e) { if (!dragging) return; onMoveXY(e.touches[0].clientX, e.touches[0].clientY); };
            var onTouchEnd = function() { if (dragging) { dragging = false; wpDiv.style.cursor = 'grab'; save(); } };
            document.addEventListener('touchmove', onTouchMove);
            document.addEventListener('touchend', onTouchEnd);
            peDragListeners.push({ type: 'touchmove', fn: onTouchMove }, { type: 'touchend', fn: onTouchEnd });
        }

        function syncPeToCursor() {
            // Write _pe state back into s.cursor (svgPath or waypoints)
            var s = cur();
            var pe = getPe();
            if (!s.cursor || pe.points.length < 2) return;

            ensureSegments(pe);

            if (pe.points.length === 2) {
                // Simple from->to with svgPath
                var seg = pe.segments[0] || defaultSegment();
                s.cursor.svgPath = 'M 0,0 C ' + seg.cp1.x.toFixed(2) + ',' + seg.cp1.y.toFixed(2)
                    + ' ' + seg.cp2.x.toFixed(2) + ',' + seg.cp2.y.toFixed(2) + ' 1,1';
                delete s.cursor.waypoints;
            } else {
                // Waypoints mode
                delete s.cursor.svgPath;
                s.cursor.waypoints = [];
                for (var i = 1; i < pe.points.length; i++) {
                    var seg = pe.segments[i - 1];
                    var svgP = 'M 0,0 C ' + seg.cp1.x.toFixed(2) + ',' + seg.cp1.y.toFixed(2)
                        + ' ' + seg.cp2.x.toFixed(2) + ',' + seg.cp2.y.toFixed(2) + ' 1,1';
                    var isLast = i === pe.points.length - 1;
                    var target = isLast ? s.cursor.to : 'screen:' + Math.round(pe.points[i].x) + ',' + Math.round(pe.points[i].y);
                    var wp = { target: target, svgPath: svgP };
                    if (pe.actions[i] && pe.actions[i] !== 'path') wp.action = pe.actions[i];
                    if (pe.pauses[i] && pe.pauses[i] > 0) wp.pause = pe.pauses[i];
                    s.cursor.waypoints.push(wp);
                }
            }
            onStepChanged(); // update output
        }


        function renderPickRow(container, label, selector, anchor, btnClass, onPick, showRefs) {
            var isRef = selector && (selector.indexOf('highlight:') === 0 || selector.indexOf('hover:') === 0);
            var displayText = selector || '\u2014';
            var display = el('span', { textContent: displayText, className: 'sb-selector', style: { flex: '1', color: isRef ? '#6c8aff' : undefined } });
            var row = makeRow([
                el('label', { textContent: label, className: 'sb-label', style: { minWidth: '40px' } }),
                display,
                anchor && !isRef ? el('span', { className: 'sb-badge', textContent: anchor.x + ',' + anchor.y }) : null,
                makeBtn('Pick', 'sb-btn-' + btnClass + ' sb-btn-sm', function() {
                    pickElement(function(sel, anc) { onPick(sel, anc); });
                }),
            ]);
            container.appendChild(row);

            // Reference buttons for linking to highlight/hover elements
            if (showRefs) {
                var s = cur();
                var refBtns = [];

                // Highlight references
                if (s.highlight && s.highlight.groups) {
                    var hlIdx = 0;
                    s.highlight.groups.forEach(function(g) {
                        var elems = g.elements || g;
                        if (Array.isArray(elems)) {
                            elems.forEach(function(hlSel) {
                                var idx = hlIdx;
                                var shortSel = hlSel.length > 25 ? hlSel.substring(0, 22) + '...' : hlSel;
                                refBtns.push(makeBtn('hl:' + idx + ' ' + shortSel, 'sb-btn-ghost sb-btn-sm', function() {
                                    onPick('highlight:' + idx, null);
                                }));
                                hlIdx++;
                            });
                        }
                    });
                }

                // Hover references
                if (s.hover) {
                    var hoverList = Array.isArray(s.hover) ? s.hover : [s.hover];
                    hoverList.forEach(function(hvSel, i) {
                        var shortSel = hvSel.length > 25 ? hvSel.substring(0, 22) + '...' : hvSel;
                        refBtns.push(makeBtn('hv:' + i + ' ' + shortSel, 'sb-btn-ghost sb-btn-sm', function() {
                            onPick('hover:' + i, null);
                        }));
                    });
                }

                if (refBtns.length) {
                    var refRow = el('div', { style: { display: 'flex', flexWrap: 'wrap', gap: '4px', marginLeft: '44px', marginBottom: '6px' } });
                    refBtns.forEach(function(btn) { refRow.appendChild(btn); });
                    container.appendChild(refRow);
                }
            }
        }

        function renderCursorFromTo(container, s, c) {
            renderPickRow(container, 'from', c.from, c.fromAnchor, 'green', function(sel, anchor) {
                ensureCursor(s);
                s.cursor.from = sel;
                s.cursor.fromAnchor = anchor;
                save(); loadPeFromCursor(); rebuildPathOverlay(); cursorSection.refresh();
            }, true);

            // Show waypoints between From and To
            if (c.waypoints && c.waypoints.length) {
                var wpList = el('div', { style: { marginLeft: '12px', borderLeft: '2px solid rgba(79,195,247,0.3)', paddingLeft: '8px', marginBottom: '4px' } });
                c.waypoints.forEach(function(wp, i) {
                    var wpRow = el('div', { style: { display: 'flex', alignItems: 'center', gap: '4px', padding: '2px 0' } });
                    var ptColor = '#4fc3f7';
                    wpRow.appendChild(el('span', { textContent: '\u25CF', style: { color: ptColor, fontSize: '10px', width: '12px' } }));
                    wpRow.appendChild(el('span', { textContent: wp.target || ('Point ' + (i + 1)), className: 'sb-selector', style: { flex: '1', fontSize: '11px' } }));
                    if (wp.action && wp.action !== 'path') {
                        wpRow.appendChild(el('span', { className: 'sb-badge', textContent: wp.action }));
                    }
                    if (wp.pause && wp.pause > 0) {
                        wpRow.appendChild(el('span', { className: 'sb-badge', textContent: wp.pause + 's' }));
                    }
                    wpList.appendChild(wpRow);
                });
                container.appendChild(wpList);
            }

            renderPickRow(container, 'to', c.to, c.toAnchor, 'red', function(sel, anchor) {
                ensureCursor(s);
                s.cursor.to = sel;
                s.cursor.toAnchor = anchor;
                save(); loadPeFromCursor(); rebuildPathOverlay(); cursorSection.refresh();
            }, true);
        }

        function renderCursorWaypoints(container, pe) {
            if (pe.points.length < 2) return;

            var wpInfo = el('div', { style: { marginBottom: '6px' } });
            for (var i = 0; i < pe.points.length; i++) {
                var wpRow = el('div', { className: 'sb-row', style: { marginBottom: '4px' } });
                var isFirst = i === 0, isLast = i === pe.points.length - 1;
                var ptLabel = isFirst ? 'From' : (isLast ? 'To' : 'Pt ' + i);
                var ptColor = isFirst ? '#4caf50' : (isLast ? '#f44336' : '#4fc3f7');
                wpRow.appendChild(el('span', { textContent: ptLabel, style: { color: ptColor, fontWeight: '700', fontSize: '11px', width: '35px' } }));
                if (i > 0) {
                    var actionSel = makeSelect(
                        [{ value: 'path' }, { value: 'click' }, { value: 'hover' }],
                        pe.actions[i] || 'path',
                        (function(idx) { return function(v) { pe.actions[idx] = v; syncPeToCursor(); }; })(i)
                    );
                    actionSel.style.width = '70px';
                    wpRow.appendChild(actionSel);
                    wpRow.appendChild(el('span', { textContent: 'pause', className: 'sb-label', style: { minWidth: 'auto' } }));
                    var pauseInput = makeNumberInput(pe.pauses[i] || 0, (function(idx) { return function(v) { pe.pauses[idx] = v; syncPeToCursor(); }; })(i), { step: '0.5', min: '0' });
                    wpRow.appendChild(pauseInput);
                    wpRow.appendChild(el('span', { textContent: 's', style: { color: '#7a7f8e', fontSize: '10px' } }));
                } else {
                    wpRow.appendChild(el('span', { textContent: 'start', style: { color: '#7a7f8e', fontSize: '11px' } }));
                }
                wpInfo.appendChild(wpRow);
            }
            container.appendChild(wpInfo);
        }

        function renderCursorControls(container, s, c, pe) {
            // + Add Point / Remove Last Point
            container.appendChild(makeRow([
                makeBtn('+ Add Point', 'sb-btn-yellow sb-btn-sm', function() {
                    if (!s.cursor || !s.cursor.from || !s.cursor.to) {
                        flashStatus('Pick From and To first!'); return;
                    }
                    pickElement(function(sel, anchor, target) {
                        var rect = target.getBoundingClientRect();
                        var pos = { x: rect.left + rect.width * anchor.x, y: rect.top + rect.height * anchor.y };
                        var pe = getPe();
                        if (pe.points.length < 2) { loadPeFromCursor(); }
                        pe.points.splice(pe.points.length - 1, 0, pos);
                        pe.actions.splice(pe.actions.length - 1, 0, 'path');
                        pe.pauses.splice(pe.pauses.length - 1, 0, 0);
                        syncPeToCursor();
                        rebuildPathOverlay();
                        cursorSection.refresh();
                    });
                }),
                makeBtn('Remove Last Pt', 'sb-btn-ghost sb-btn-sm', function() {
                    var pe = getPe();
                    if (pe.points.length > 2) {
                        pe.points.splice(pe.points.length - 2, 1);
                        pe.actions.splice(pe.actions.length - 2, 1);
                        pe.pauses.splice(pe.pauses.length - 2, 1);
                        pe.segments.pop();
                        syncPeToCursor();
                        rebuildPathOverlay();
                        cursorSection.refresh();
                    }
                }),
            ]));

            // Duration / Ease
            var durationInput = makeNumberInput(c.duration || 1.5, function(v) {
                ensureCursor(s);
                s.cursor.duration = v; save();
            }, { step: '0.1', min: '0.1' });
            var easeSelect = makeSelect(
                [{ value: 'power2.inOut' }, { value: 'power2.out' }, { value: 'power2.in' },
                 { value: 'power1.inOut' }, { value: 'power3.inOut' }, { value: 'power3.out' },
                 { value: 'power4.inOut' },
                 { value: 'elastic.out' }, { value: 'back.out' }, { value: 'back.inOut' },
                 { value: 'bounce.out' }, { value: 'circ.inOut' }, { value: 'expo.inOut' },
                 { value: 'sine.inOut' }, { value: 'linear' }, { value: 'none' }],
                c.ease || 'power2.inOut',
                function(v) { ensureCursor(s); s.cursor.ease = v; save(); }
            );
            easeSelect.style.width = '110px';
            container.appendChild(makeRow([
                el('label', { textContent: 'dur', className: 'sb-label', style: { minWidth: '30px' } }),
                durationInput,
                el('label', { textContent: 'ease', className: 'sb-label', style: { minWidth: 'auto', marginLeft: '6px' } }),
                easeSelect,
            ]));

            // click / loop
            container.appendChild(makeRow([
                makeCheckbox('click', !!c.click, function(v) { ensureCursor(s); s.cursor.click = v; save(); }),
                makeCheckbox('loop', !!c.loop, function(v) { ensureCursor(s); s.cursor.loop = v; save(); }),
                (function() {
                    var lbl = el('label', { style: { display: 'inline-flex', alignItems: 'center', gap: '5px', fontSize: '14px', color: '#e74c3c', cursor: 'pointer', marginLeft: '8px' } });
                    var cb = el('input', { type: 'checkbox', checked: !!window._peRealClick });
                    cb.addEventListener('change', function() { window._peRealClick = cb.checked; });
                    lbl.appendChild(cb);
                    lbl.appendChild(document.createTextNode('real click'));
                    return lbl;
                })(),
            ]));

            // svgPath (readonly display)
            if (c.svgPath) {
                var pathInput = el('input', { type: 'text', value: c.svgPath, readOnly: true, style: { opacity: '0.5', fontSize: '10px' } });
                container.appendChild(makeLabeledRow('svgPath', pathInput));
            }

            // Drag hint
            if (pe.points.length >= 2) {
                container.appendChild(el('div', { style: { color: '#666', fontSize: '10px', marginBottom: '6px' },
                    innerHTML: 'Drag <span style="color:#ff9800">\u25CF</span>/<span style="color:#e91e63">\u25CF</span> handles to shape curves. <span style="color:#4caf50">\u25CF</span> from, <span style="color:#f44336">\u25CF</span> to, <span style="color:#4fc3f7">\u25CF</span> waypoints.' }));
            }

            // Preview / Clear
            container.appendChild(makeRow([
                makeBtn('\u25B6 Preview', 'sb-btn-blue sb-btn-sm', function() {
                    if (ctx.activeCursor()) { ctx.activeCursor().destroy(); ctx.setActiveCursor(null); }
                    if (s.cursor && s.cursor.from && s.cursor.to) {
                        var mockStep = { cursor: s.cursor };
                        var result = s.cursor.waypoints
                            ? TutorialEngine.animateCursorWaypoints(mockStep, ctx.overlay)
                            : TutorialEngine.animateCursor(mockStep, ctx.overlay);
                        ctx.setActiveCursor(result);
                    }
                }),
                makeBtn('Show Path', 'sb-btn-ghost sb-btn-sm', function() {
                    if (s.cursor && s.cursor.from) {
                        loadPeFromCursor();
                        rebuildPathOverlay();
                    }
                }),
                makeBtn('Hide Path', 'sb-btn-ghost sb-btn-sm', function() {
                    clearPathOverlay();
                }),
                makeBtn('Clear', 'sb-btn-red sb-btn-sm', function() {
                    s.cursor = null;
                    s._pe = null;
                    clearPathOverlay();
                    if (ctx.activeCursor()) { ctx.activeCursor().destroy(); ctx.setActiveCursor(null); }
                    saveRefresh(cursorSection);
                }),
            ]));

            // Auto-show path if cursor has from+to
            if (c.from && c.to && pe.points.length < 2) {
                loadPeFromCursor();
                rebuildPathOverlay();
            } else if (pe.points.length >= 2) {
                rebuildPathOverlay();
            }
        }

        function renderCursorContent(container) {
            container.innerHTML = '';
            var s = cur();
            var c = s.cursor || {};
            var pe = getPe();

            renderCursorFromTo(container, s, c);
            renderCursorWaypoints(container, pe);
            renderCursorControls(container, s, c, pe);
        }

        // =============================================
        // 5. HIGHLIGHT SECTION
        // =============================================
        var highlightSection = makeSection('\uD83D\uDD26', 'Highlight', renderHighlightContent);
        body.appendChild(highlightSection.wrapper);

        function renderHighlightContent(container) {
            container.innerHTML = '';
            var s = cur();
            var h = s.highlight || {};
            var groups = h.groups || [];

            // List selectors
            var allSelectors = [];
            groups.forEach(function(g) {
                var elems = g.elements || g;
                if (Array.isArray(elems)) {
                    elems.forEach(function(sel) { allSelectors.push(sel); });
                }
            });

            if (allSelectors.length) {
                var mode = h._mode || 'together';
                if (!h._groups) h._groups = allSelectors.map(function() { return 1; });
                while (h._groups.length < allSelectors.length) h._groups.push(1);
                var listDiv = el('div', { style: { marginBottom: '8px' } });
                allSelectors.forEach(function(sel, i) {
                    var row = el('div', { style: { display: 'flex', alignItems: 'center', gap: '4px', padding: '3px 0' } });
                    row.appendChild(el('span', { textContent: (i + 1) + '.', style: { color: '#7a7f8e', fontSize: '10px', width: '16px' } }));
                    row.appendChild(el('span', { textContent: sel, className: 'sb-selector', style: { flex: '1' } }));
                    if (mode === 'custom') {
                        row.appendChild(el('span', { textContent: 'grp:', style: { color: '#7a7f8e', fontSize: '10px' } }));
                        var grpInput = makeNumberInput(h._groups[i] || 1, (function(idx) {
                            return function(v) { h._groups[idx] = Math.max(1, v); rebuildHighlightGroups(s); save(); };
                        })(i), { min: '1', step: '1' });
                        grpInput.style.width = '55px';
                        row.appendChild(grpInput);
                    }
                    listDiv.appendChild(row);
                });
                container.appendChild(listDiv);
            }

            // Mode
            var modeSelect = makeSelect([{ value: 'together' }, { value: 'separate' }, { value: 'custom', label: 'Custom groups' }], (h._mode || 'together'), function(v) {
                ensureHighlight(s);
                s.highlight._mode = v;
                rebuildHighlightGroups(s);
                saveRefresh(highlightSection);
            });
            modeSelect.style.width = '100px';
            container.appendChild(makeRow([
                el('label', { textContent: 'mode', className: 'sb-label' }),
                modeSelect,
            ]));

            // Pick / Remove
            container.appendChild(makeRow([
                makeBtn('+ Pick Element', 'sb-btn-yellow sb-btn-sm', function() {
                    pickElement(function(sel) {
                        ensureHighlight(s);
                        if (!s.highlight._selectors) s.highlight._selectors = [];
                        s.highlight._selectors.push(sel);
                        rebuildHighlightGroups(s);
                        saveRefresh(highlightSection);
                    });
                }),
                makeBtn('Remove Last', 'sb-btn-ghost sb-btn-sm', function() {
                    if (s.highlight && s.highlight._selectors && s.highlight._selectors.length) {
                        s.highlight._selectors.pop();
                        rebuildHighlightGroups(s);
                        saveRefresh(highlightSection);
                    }
                }),
            ]));

            // blockOutside option
            container.appendChild(makeRow([
                makeCheckbox('blockOutside', !!h.blockOutside, function(v) {
                    if (!s.highlight) s.highlight = {};
                    s.highlight.blockOutside = v || undefined;
                    save(true);
                }),
            ]));

            // Preview / Clear
            container.appendChild(makeRow([
                makeBtn('\u25B6 Preview', 'sb-btn-blue sb-btn-sm', function() {
                    if (ctx.activeHighlight()) { ctx.activeHighlight().destroy(); ctx.setActiveHighlight(null); }
                    if (s.highlight && s.highlight.groups && s.highlight.groups.length) {
                        var result = TutorialEngine.createHighlight(s.highlight);
                        ctx.setActiveHighlight(result);
                    }
                }),
                makeBtn('Clear', 'sb-btn-red sb-btn-sm', function() {
                    s.highlight = null;
                    if (ctx.activeHighlight()) { ctx.activeHighlight().destroy(); ctx.setActiveHighlight(null); }
                    saveRefresh(highlightSection);
                }),
            ]));
        }

        function rebuildHighlightGroups(step) {
            if (!step.highlight) return;
            var selectors = step.highlight._selectors || [];
            var mode = step.highlight._mode || 'together';
            if (mode === 'together') {
                step.highlight.groups = selectors.length ? [{ elements: selectors.slice() }] : [];
            } else if (mode === 'separate') {
                step.highlight.groups = selectors.map(function(s) { return { elements: [s] }; });
            } else if (mode === 'custom') {
                var gMap = {};
                var groups = step.highlight._groups || [];
                selectors.forEach(function(sel, i) {
                    var g = groups[i] || 1;
                    if (!gMap[g]) gMap[g] = [];
                    gMap[g].push(sel);
                });
                step.highlight.groups = [];
                Object.keys(gMap).sort(function(a, b) { return a - b; }).forEach(function(k) {
                    step.highlight.groups.push({ elements: gMap[k] });
                });
            }
        }

        function initHighlightSelectors(s) {
            if (s.highlight && s.highlight.groups && !s.highlight._selectors) {
                s.highlight._selectors = [];
                s.highlight.groups.forEach(function(g) {
                    var elems = g.elements || g;
                    if (Array.isArray(elems)) {
                        elems.forEach(function(sel) { s.highlight._selectors.push(sel); });
                    }
                });
                if (!s.highlight._mode) s.highlight._mode = 'together';
            }
        }

        // =============================================
        // 5b. HOVER SECTION
        // =============================================
        var hoverSection = makeSection('\uD83D\uDC46', 'Hover', renderHoverContent);
        body.appendChild(hoverSection.wrapper);

        function renderHoverContent(container) {
            container.innerHTML = '';
            var s = cur();
            var hoverList = s.hover ? (Array.isArray(s.hover) ? s.hover : [s.hover]) : [];

            // List current hover selectors
            if (hoverList.length) {
                var listDiv = el('div', { style: { marginBottom: '8px' } });
                hoverList.forEach(function(sel, i) {
                    var row = el('div', { style: { display: 'flex', alignItems: 'center', gap: '4px', padding: '3px 0' } });
                    row.appendChild(el('span', { textContent: (i + 1) + '.', style: { color: '#7a7f8e', fontSize: '10px', width: '16px' } }));
                    row.appendChild(el('span', { textContent: sel, className: 'sb-selector', style: { flex: '1' } }));
                    row.appendChild(makeBtn('\u2715', 'sb-btn-ghost sb-btn-icon sb-btn-sm', function() {
                        hoverList.splice(i, 1);
                        s.hover = hoverList.length ? (hoverList.length === 1 ? hoverList[0] : hoverList) : undefined;
                        saveRefresh(hoverSection);
                    }));
                    listDiv.appendChild(row);
                });
                container.appendChild(listDiv);
            }

            // Pick / Clear
            container.appendChild(makeRow([
                makeBtn('+ Pick Element', 'sb-btn-yellow sb-btn-sm', function() {
                    pickElement(function(sel) {
                        // Walk up to the nearest parent that has :hover CSS rules
                        var picked = document.querySelector(sel);
                        if (picked) {
                            var hoverable = TutorialEngine.findHoverableParent(picked);
                            if (hoverable && hoverable !== picked) {
                                var betterSel = TutorialEngine.bestSelector(hoverable);
                                console.log('[Hover Pick] Upgraded selector from', sel, 'to', betterSel);
                                sel = betterSel;
                            } else if (!hoverable) {
                                console.warn('[Hover Pick] No :hover CSS rules found on element or ancestors for', sel);
                            }
                        }
                        var s2 = cur();
                        var live = s2.hover ? (Array.isArray(s2.hover) ? s2.hover : [s2.hover]) : [];
                        live.push(sel);
                        s2.hover = live.length === 1 ? live[0] : live;
                        saveRefresh(hoverSection);
                    });
                }),
                makeBtn('Clear', 'sb-btn-red sb-btn-sm', function() {
                    s.hover = undefined;
                    saveRefresh(hoverSection);
                }),
            ]));

            // Preview
            container.appendChild(makeRow([
                makeBtn('\u25B6 Preview', 'sb-btn-blue sb-btn-sm', function() {
                    var s2 = cur();
                    var liveList = s2.hover ? (Array.isArray(s2.hover) ? s2.hover : [s2.hover]) : [];
                    // Clean previous
                    document.querySelectorAll('.tutorial-force-hover').forEach(function(hEl) {
                        TutorialEngine.removeForceHoverStyles(hEl);
                        hEl.classList.remove('tutorial-force-hover');
                    });
                    liveList.forEach(function(sel) {
                        var hEl = document.querySelector(sel);
                        if (hEl) {
                            TutorialEngine.forceHoverStyles(hEl);
                            hEl.classList.add('tutorial-force-hover');
                            hEl.dispatchEvent(new MouseEvent('mouseenter', { bubbles: true }));
                            hEl.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));
                        }
                    });
                }),
                makeBtn('Stop', 'sb-btn-ghost sb-btn-sm', function() {
                    document.querySelectorAll('.tutorial-force-hover').forEach(function(hEl) {
                        TutorialEngine.removeForceHoverStyles(hEl);
                        hEl.classList.remove('tutorial-force-hover');
                        hEl.dispatchEvent(new MouseEvent('mouseleave', { bubbles: true }));
                        hEl.dispatchEvent(new MouseEvent('mouseout', { bubbles: true }));
                    });
                }),
            ]));
        }

        // =============================================
        // 6. SCROLL SECTION
        // =============================================
        var scrollSection = makeSection('\uD83D\uDCDC', 'Scroll', renderScrollContent);
        body.appendChild(scrollSection.wrapper);

        function renderScrollContent(container) {
            container.innerHTML = '';
            var s = cur();

            // scrollTo
            var scrollToDisplay = el('span', { textContent: s.scrollTo || '\u2014', className: 'sb-selector', style: { flex: '1' } });
            container.appendChild(makeRow([
                el('label', { textContent: 'scrollTo', className: 'sb-label', style: { minWidth: '70px' } }),
                scrollToDisplay,
                makeBtn('Pick', 'sb-btn-green sb-btn-sm', function() {
                    pickElement(function(sel) { s.scrollTo = sel; saveRefresh(scrollSection); });
                }),
            ]));

            // scrollInside
            var si = s.scrollInside || {};
            var siDisplay = el('span', { textContent: si.selector || '\u2014', className: 'sb-selector', style: { flex: '1' } });
            container.appendChild(makeRow([
                el('label', { textContent: 'inside', className: 'sb-label', style: { minWidth: '70px' } }),
                siDisplay,
                makeBtn('Pick', 'sb-btn-green sb-btn-sm', function() {
                    pickElement(function(sel) {
                        ensureScrollInside(s);
                        s.scrollInside.selector = sel;
                        saveRefresh(scrollSection);
                    });
                }),
            ]));

            if (si.selector) {
                var modeSelect = makeSelect(
                    [{ value: 'to', label: 'to' }, { value: 'by', label: 'by' }],
                    si.to !== undefined ? 'to' : 'by',
                    function(v) {
                        ensureScrollInside(s);
                        if (v === 'to') { s.scrollInside.to = s.scrollInside.by || 300; delete s.scrollInside.by; }
                        else { s.scrollInside.by = s.scrollInside.to || 300; delete s.scrollInside.to; }
                        saveRefresh(scrollSection);
                    }
                );
                modeSelect.style.width = '55px';
                container.appendChild(makeRow([
                    modeSelect,
                    makeNumberInput(si.to !== undefined ? si.to : (si.by || 300), function(v) {
                        ensureScrollInside(s);
                        if (si.to !== undefined) s.scrollInside.to = v; else s.scrollInside.by = v;
                        save();
                    }, { step: '50' }),
                    el('label', { textContent: 'dur', className: 'sb-label', style: { minWidth: 'auto' } }),
                    makeNumberInput(si.duration || 1, function(v) {
                        ensureScrollInside(s);
                        s.scrollInside.duration = v; save();
                    }, { step: '0.1', min: '0.1' }),
                    el('span', { textContent: 's', style: { color: '#7a7f8e', fontSize: '10px' } }),
                ]));
            }

            // Test / Clear
            container.appendChild(makeRow([
                makeBtn('\u25B6 Test', 'sb-btn-blue sb-btn-sm', function() {
                    if (s.scrollTo) {
                        var target = document.querySelector(s.scrollTo);
                        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    if (s.scrollInside && s.scrollInside.selector) {
                        var scrollEl = document.querySelector(s.scrollInside.selector);
                        if (scrollEl && typeof gsap !== 'undefined') {
                            var amount = s.scrollInside.to !== undefined ? s.scrollInside.to : (scrollEl.scrollTop + (s.scrollInside.by || 300));
                            gsap.to(scrollEl, { scrollTop: amount, duration: s.scrollInside.duration || 1, ease: 'power2.inOut' });
                        }
                    }
                }),
                makeBtn('Clear', 'sb-btn-red sb-btn-sm', function() {
                    delete s.scrollTo;
                    delete s.scrollInside;
                    saveRefresh(scrollSection);
                }),
            ]));
        }

        // =============================================
        // 7. OPTIONS SECTION
        // =============================================
        var optionsSection = makeSection('\uD83D\uDDC2\uFE0F', 'Options', renderOptionsContent);
        body.appendChild(optionsSection.wrapper);

        function renderOptionsContent(container) {
            container.innerHTML = '';
            var s = cur();
            if (!s.options) s.options = [];

            s.options.forEach(function(opt, i) {
                var row = el('div', { className: 'sb-option-row' });

                var labelInput = makeInput(opt.label || '', function(v) { opt.label = v; save(); }, { placeholder: 'Label key' });
                labelInput.style.flex = '1';
                row.appendChild(labelInput);

                var actionVal = opt.redirect ? 'redirect' : (opt.goToStep !== undefined ? 'goToStep' : (opt.done ? 'done' : 'next'));
                var actionSel = makeSelect(
                    [{ value: 'next', label: 'Next' }, { value: 'goToStep', label: 'Go to' }, { value: 'redirect', label: 'URL' }, { value: 'done', label: 'Done' }],
                    actionVal,
                    function(v) {
                        delete opt.redirect; delete opt.goToStep; delete opt.done;
                        if (v === 'redirect') opt.redirect = '';
                        else if (v === 'goToStep') opt.goToStep = 0;
                        else if (v === 'done') opt.done = true;
                        saveRefresh(optionsSection);
                    }
                );
                actionSel.style.width = '70px';
                row.appendChild(actionSel);

                if (opt.redirect !== undefined) {
                    var valInput = makeInput(opt.redirect, function(v) { opt.redirect = v; save(); }, { placeholder: '/path' });
                    valInput.style.width = '70px';
                    row.appendChild(valInput);
                    row.appendChild(makeCheckbox('tutorial', !!opt.startTutorial, function(v) { opt.startTutorial = v || undefined; save(); }));
                } else if (opt.goToStep !== undefined) {
                    row.appendChild(makeNumberInput(opt.goToStep, function(v) { opt.goToStep = v; save(); }, { min: '0', step: '1' }));
                }

                row.appendChild(makeBtn('\u2715', 'sb-btn-red sb-btn-icon sb-btn-sm', function() {
                    s.options.splice(i, 1); saveRefresh(optionsSection);
                }));

                container.appendChild(row);
            });

            container.appendChild(makeRow([
                makeBtn('+ Add Option', 'sb-btn-green sb-btn-sm', function() {
                    s.options.push({ label: '', goToStep: 0 });
                    saveRefresh(optionsSection);
                }),
                makeBtn('\u25B6 Preview', 'sb-btn-blue sb-btn-sm', function() {
                    if (s.options.length && ctx.optionsRow) {
                        ctx.optionsRow.innerHTML = '';
                        ctx.optionsRow.style.display = 'flex';
                        s.options.forEach(function(opt, oi) {
                            var optBtn = el('button', {
                                textContent: opt.label || '(empty)',
                                style: {
                                    padding: '0.6rem 1rem', backgroundColor: '#f0f4ff', color: '#07499e',
                                    border: '2px solid #07499e', borderRadius: '10px', cursor: 'pointer',
                                    fontSize: '14px', fontWeight: '600', opacity: '0', transform: 'translateY(8px)',
                                },
                            });
                            ctx.optionsRow.appendChild(optBtn);
                            if (typeof gsap !== 'undefined') {
                                gsap.to(optBtn, { opacity: 1, y: 0, duration: 0.3, delay: 0.1 * (oi + 1), ease: 'power2.out' });
                            } else {
                                optBtn.style.opacity = '1'; optBtn.style.transform = 'none';
                            }
                        });
                    }
                }),
            ]));
        }

        // =============================================
        // 8. OUTPUT SECTION
        // =============================================
        var outputSection = makeSection('\uD83D\uDCBE', 'Output', function() {});
        body.appendChild(outputSection.wrapper);
        var outputPre = el('pre', {
            className: 'sb-code',
            style: {
                background: 'rgba(0,0,0,0.3)', padding: '10px 12px', borderRadius: '8px', fontSize: '10px',
                overflowX: 'auto', maxHeight: '250px', whiteSpace: 'pre-wrap', color: '#8bc78b',
                margin: '0', border: '1px solid rgba(255,255,255,0.04)', lineHeight: '1.6',
            },
        });
        outputSection.content.appendChild(outputPre);

        // =============================================
        // CODE GENERATION
        // =============================================
        //          may embed strings in either single or double quotes depending on context.
        function generateStepCode(s) {
            var lines = [];
            lines.push('{');
            if (s.silentClick) {
                lines.push('    silentClick: \'' + escStr(s.silentClick) + '\',');
                lines.push('}');
                return lines.join('\n');
            }
            if (s.html) lines.push('    html: \'' + escStr(s.html) + '\',');
            if (s.overlay === false) lines.push('    overlay: false,');
            if (typeof s.showBack === 'number') lines.push('    showBack: ' + s.showBack + ',');
            else if (s.showBack) lines.push('    showBack: true,');
            if (s.position && s.position !== 'left') lines.push('    position: \'' + s.position + '\',');
            if (s.align && s.align !== 'center') lines.push('    align: \'' + s.align + '\',');
            if (s.chatMode) lines.push('    chatMode: true,');
            if (s.chatMaxWidth) lines.push('    chatMaxWidth: \'' + escStr(s.chatMaxWidth) + '\',');
            if (s.clearOverlay) lines.push('    clearOverlay: true,');
            if (s.positionTarget) lines.push('    positionTarget: \'' + escStr(s.positionTarget) + '\',');
            if (s.showIf) lines.push('    showIf: \'' + escStr(s.showIf) + '\',');

            if (s.cursor && s.cursor.from) {
                lines.push('    cursor: {');
                lines.push('        from: \'' + escStr(s.cursor.from) + '\',');
                if (s.cursor.fromAnchor) lines.push('        fromAnchor: { x: ' + s.cursor.fromAnchor.x + ', y: ' + s.cursor.fromAnchor.y + ' },');
                if (s.cursor.to) {
                    lines.push('        to: \'' + escStr(s.cursor.to) + '\',');
                    if (s.cursor.toAnchor) lines.push('        toAnchor: { x: ' + s.cursor.toAnchor.x + ', y: ' + s.cursor.toAnchor.y + ' },');
                }
                if (s.cursor.svgPath && !s.cursor.waypoints) lines.push('        svgPath: \'' + escStr(s.cursor.svgPath) + '\',');
                if (s.cursor.duration && s.cursor.duration !== 1.5) lines.push('        duration: ' + s.cursor.duration + ',');
                if (s.cursor.delay && s.cursor.delay > 0) lines.push('        delay: ' + s.cursor.delay + ',');
                if (s.cursor.ease && s.cursor.ease !== 'power2.out') lines.push('        ease: \'' + s.cursor.ease + '\',');
                if (s.cursor.click) lines.push('        click: true,');
                if (s.cursor.loop) lines.push('        loop: true,');
                if (s.cursor.waypoints && s.cursor.waypoints.length) {
                    lines.push('        waypoints: [');
                    s.cursor.waypoints.forEach(function(wp) {
                        var parts = ['target: \'' + escStr(wp.target) + '\''];
                        if (wp.svgPath) parts.push('svgPath: \'' + escStr(wp.svgPath) + '\'');
                        if (wp.action && wp.action !== 'path') parts.push('action: \'' + wp.action + '\'');
                        if (wp.pause && wp.pause > 0) parts.push('pause: ' + wp.pause);
                        lines.push('            { ' + parts.join(', ') + ' },');
                    });
                    lines.push('        ],');
                }
                lines.push('    },');
            }

            if (s.highlight && s.highlight.groups && s.highlight.groups.length) {
                lines.push('    highlight: {');
                lines.push('        groups: [');
                s.highlight.groups.forEach(function(g) {
                    var elems = g.elements || g;
                    if (Array.isArray(elems)) {
                        lines.push('            [\'' + elems.map(escStr).join('\', \'') + '\'],');
                    }
                });
                lines.push('        ],');
                lines.push('        padding: ' + (s.highlight.padding || 8) + ',');
                lines.push('        borderRadius: ' + (s.highlight.borderRadius || 8) + ',');
                if (s.highlight.blockOutside) lines.push('        blockOutside: true,');
                lines.push('    },');
            }

            if (s.hover) {
                var hList = Array.isArray(s.hover) ? s.hover : [s.hover];
                if (hList.length === 1) {
                    lines.push('    hover: \'' + escStr(hList[0]) + '\',');
                } else if (hList.length > 1) {
                    lines.push('    hover: [\'' + hList.map(escStr).join('\', \'') + '\'],');
                }
            }

            if (s.scrollTo) lines.push('    scrollTo: \'' + escStr(s.scrollTo) + '\',');

            if (s.scrollInside && s.scrollInside.selector) {
                lines.push('    scrollInside: {');
                lines.push('        selector: \'' + escStr(s.scrollInside.selector) + '\',');
                if (s.scrollInside.to !== undefined) lines.push('        to: ' + s.scrollInside.to + ',');
                if (s.scrollInside.by !== undefined) lines.push('        by: ' + s.scrollInside.by + ',');
                if (s.scrollInside.duration) lines.push('        duration: ' + s.scrollInside.duration + ',');
                lines.push('    },');
            }

            if (s.options && s.options.length) {
                lines.push('    options: [');
                s.options.forEach(function(opt) {
                    var parts = [];
                    if (opt.label) parts.push('label: \'' + escStr(opt.label) + '\'');
                    if (opt.done) parts.push('done: true');
                    else if (opt.redirect !== undefined) {
                        parts.push('redirect: \'' + escStr(opt.redirect) + '\'');
                        if (opt.startTutorial) parts.push('startTutorial: true');
                    }
                    else if (opt.goToStep !== undefined) parts.push('goToStep: ' + opt.goToStep);
                    lines.push('        { ' + parts.join(', ') + ' },');
                });
                lines.push('    ],');
            }

            if (s.advance === 'auto') {
                lines.push('    autoNext: ' + (s.autoNextDelay || 3) + ',');
            } else if (s.advance === 'afterAnimation') {
                lines.push('    afterAnimation: true,');
            }

            lines.push('}');
            return lines.join('\n');
        }

        function generateFullCode() {
            var lines = [];
            lines.push('$(document).ready(function(){');
            lines.push('');
            lines.push('    var steps = [');
            peSteps.forEach(function(s, i) {
                lines.push('        // Step ' + i);
                var stepCode = generateStepCode(s);
                var stepLines = stepCode.split('\n');
                stepLines.forEach(function(l) { lines.push('        ' + l); });
                // Add comma after closing brace
                var last = lines.length - 1;
                if (lines[last].trim() === '}') lines[last] = lines[last] + ',';
            });
            lines.push('    ];');
            lines.push('');
            lines.push('    TutorialEngine.start(steps, {');
            lines.push('        nextLabel: \'tutorial.next\',');
            lines.push('        doneLabel: \'tutorial.done\',');
            if (ctx.opts.bubbleMaxWidth !== initialOpts.bubbleMaxWidth) {
                lines.push('        bubbleMaxWidth: \'' + ctx.opts.bubbleMaxWidth + '\',');
                lines.push('        bubbleMinWidth: \'' + ctx.opts.bubbleMinWidth + '\',');
            }
            if (ctx.opts.bubbleFontSize !== initialOpts.bubbleFontSize) {
                lines.push('        bubbleFontSize: \'' + ctx.opts.bubbleFontSize + '\',');
            }
            lines.push('    });');
            lines.push('');
            lines.push('});');
            return lines.join('\n');
        }

        // the generated code may use either quote style in different contexts.
        function escStr(s) {
            return String(s || '').replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/'/g, "\\'");
        }

        function copyToClipboard(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(
                    function() { flashStatus('\u2705 Copied!'); },
                    function() { flashStatus('\u274C Copy failed'); }
                );
            } else {
                var ta = document.createElement('textarea');
                ta.value = text; document.body.appendChild(ta); ta.select();
                document.execCommand('copy'); document.body.removeChild(ta);
                flashStatus('\u2705 Copied!');
            }
        }

        function flashStatus(msg) {
            var flash = el('div', {
                textContent: msg,
                style: {
                    position: 'fixed', bottom: '20px', left: '50%', transform: 'translateX(-50%)',
                    background: 'linear-gradient(135deg, #2ecc71, #27ae60)', color: '#fff',
                    padding: '8px 20px', borderRadius: '10px', fontSize: '13px', fontWeight: '600',
                    zIndex: '100001', boxShadow: '0 4px 16px rgba(46,204,113,0.3)',
                    opacity: '1', transition: 'opacity 0.4s ease',
                },
            });
            document.body.appendChild(flash);
            setTimeout(function() { flash.style.opacity = '0'; }, 1500);
            setTimeout(function() { if (flash.parentNode) flash.parentNode.removeChild(flash); }, 2000);
        }

        // =============================================
        // STEP SELECTION & REFRESH
        // =============================================
        function selectStep(index) {
            clearPathOverlay();
            selectedIndex = Math.max(0, Math.min(index, peSteps.length - 1));
            initHighlightSelectors(cur());
            refreshAll();
            ctx.showStep(selectedIndex);
        }

        function onStepChanged() {
            renderStepList();
            updateOutput();
        }

        function refreshAll() {
            stepDetailLabel.textContent = 'Step ' + selectedIndex + ' / ' + peSteps.length;
            renderStepList();
            renderBaseConfig();
            cursorSection.refresh();
            highlightSection.refresh();
            hoverSection.refresh();
            scrollSection.refresh();
            optionsSection.refresh();
            updateOutput();
        }

        function updateOutput() {
            outputPre.textContent = generateFullCode();
        }

        // =============================================
        // SPECTATOR MODE
        // =============================================
        function enterSpectatorMode() {
            // Sync current peSteps to live steps
            syncToLiveSteps();

            // Hide panel and path overlay
            clearPathOverlay();
            panel.style.display = 'none';
            svgOverlay.style.display = 'none';

            // Restart tutorial from step 0 with clean state
            ctx.showStep(0);

            // Create a small floating "Exit Spectator" button
            var exitBtn = document.createElement('button');
            exitBtn.textContent = '\u2715 Exit Spectator';
            Object.assign(exitBtn.style, {
                position: 'fixed', top: '16px', right: '16px', zIndex: '100001',
                background: 'rgba(0,0,0,0.7)', color: '#fff', border: '1px solid rgba(255,255,255,0.2)',
                borderRadius: '8px', padding: '8px 16px', fontSize: '13px', fontWeight: '600',
                cursor: 'pointer', backdropFilter: 'blur(8px)',
                transition: 'opacity 0.2s', opacity: '0.85',
            });
            exitBtn.addEventListener('mouseenter', function() { exitBtn.style.opacity = '1'; });
            exitBtn.addEventListener('mouseleave', function() { exitBtn.style.opacity = '0.85'; });
            document.body.appendChild(exitBtn);

            // Also allow Escape to exit
            function onEscSpec(e) {
                if (e.key === 'Escape') exitSpectator();
            }
            document.addEventListener('keydown', onEscSpec);

            function exitSpectator() {
                document.removeEventListener('keydown', onEscSpec);
                if (exitBtn.parentNode) exitBtn.parentNode.removeChild(exitBtn);
                panel.style.display = 'flex';
                // Restore to current selected step
                ctx.showStep(selectedIndex);
                refreshAll();
            }

            exitBtn.addEventListener('click', exitSpectator);
        }

        // =============================================
        // INIT
        // =============================================
        peSteps.forEach(function(s) { initHighlightSelectors(s); });
        document.body.appendChild(panel);
        refreshAll();

        // --- Sync step highlight when tutorial navigates externally ---
        ctx.overlay.addEventListener('tutorial-step-change', function(e) {
            if (e.detail && typeof e.detail.index === 'number' && e.detail.index !== selectedIndex) {
                clearPathOverlay();
                selectedIndex = e.detail.index;
                initHighlightSelectors(cur());
                refreshAll();
            }
        });

        // --- Cleanup when tutorial overlay is removed ---
        var origOverlayRemove = ctx.overlay.remove.bind(ctx.overlay);
        ctx.overlay.remove = function() {
            clearPathOverlay();
            cleanupScrollResize();
            if (panel.parentNode) panel.parentNode.removeChild(panel);
            if (svgOverlay.parentNode) svgOverlay.parentNode.removeChild(svgOverlay);
            if (styleEl.parentNode) styleEl.parentNode.removeChild(styleEl);
            origOverlayRemove();
        };

    }); // end _onReady

    } // end _initStepBuilder
    _waitForEngine();

})();
