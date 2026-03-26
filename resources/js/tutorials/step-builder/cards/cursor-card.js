// cards/cursor-card.js — Cursor card (from/to, waypoints, duration, delay, ease, click, loop, image)

import { registerCard } from '../ui/cards';
import { el, makeBtn, makeInput, makeNumberInput, makeSelect, makeCheckbox, makeRow, makeLabeledRow, makeSelectorRow } from '../helpers';
import { iconEl } from '../icons';
import * as state from '../state';
import * as events from '../events';

function ensureCursor(step) {
    if (!step.cursor) step.cursor = {};
}

registerCard({
    name: 'cursor',
    icon: 'cursor',
    title: 'Cursor',
    defaultVisible: true,
    hasData: function(step) {
        return !!(step.cursor && step.cursor.from);
    },
    onDisable: function(step) {
        state.updateStep(state.getSelectedIndex(), 'cursor', undefined);
    },
    render: function(container, step) {
        var idx = state.getSelectedIndex();
        var c = step.cursor || {};

        // From
        container.appendChild(makeSelectorRow('From', c.from, 'green', function() {
            events.emit('pick-element', { callback: function(sel, anchor) {
                ensureCursor(step);
                var cursor = Object.assign({}, step.cursor, { from: sel, fromAnchor: anchor });
                state.updateStep(idx, 'cursor', cursor);
            }});
        }, c.from ? function() {
            var cursor = Object.assign({}, step.cursor);
            delete cursor.from;
            delete cursor.fromAnchor;
            state.updateStep(idx, 'cursor', cursor);
        } : null));

        // From anchor badge
        if (c.fromAnchor) {
            container.appendChild(el('div', { style: { marginBottom: '8px' } }, [
                el('span', { className: 'sb-badge', textContent: 'anchor: ' + c.fromAnchor.x + ',' + c.fromAnchor.y }),
            ]));
        }

        // To
        container.appendChild(makeSelectorRow('To', c.to, 'primary', function() {
            events.emit('pick-element', { callback: function(sel, anchor) {
                ensureCursor(step);
                var cursor = Object.assign({}, step.cursor, { to: sel, toAnchor: anchor });
                state.updateStep(idx, 'cursor', cursor);
            }});
        }, c.to ? function() {
            var cursor = Object.assign({}, step.cursor);
            delete cursor.to;
            delete cursor.toAnchor;
            state.updateStep(idx, 'cursor', cursor);
        } : null));

        // To anchor badge
        if (c.toAnchor) {
            container.appendChild(el('div', { style: { marginBottom: '8px' } }, [
                el('span', { className: 'sb-badge', textContent: 'anchor: ' + c.toAnchor.x + ',' + c.toAnchor.y }),
            ]));
        }

        // Reference buttons (highlight:N, hover:N)
        var refBtns = [];
        if (step.highlight && step.highlight.groups) {
            step.highlight.groups.forEach(function(g, gi) {
                var elems = g.elements || g;
                if (Array.isArray(elems)) {
                    elems.forEach(function(sel, ei) {
                        refBtns.push(makeBtn('hl:' + gi + '.' + ei, 'sb-btn-ghost sb-btn-sm', function() {
                            ensureCursor(step);
                            var cursor = Object.assign({}, step.cursor, { from: 'highlight:' + gi + ':' + ei });
                            state.updateStep(idx, 'cursor', cursor);
                        }));
                    });
                }
            });
        }
        if (step.hover) {
            var hovers = Array.isArray(step.hover) ? step.hover : [step.hover];
            hovers.forEach(function(h, hi) {
                refBtns.push(makeBtn('hv:' + hi, 'sb-btn-ghost sb-btn-sm', function() {
                    ensureCursor(step);
                    var cursor = Object.assign({}, step.cursor, { to: 'hover:' + hi });
                    state.updateStep(idx, 'cursor', cursor);
                }));
            });
        }
        if (refBtns.length) {
            var refRow = el('div', { style: { display: 'flex', flexWrap: 'wrap', gap: '4px', marginBottom: '8px' } });
            refBtns.forEach(function(btn) { refRow.appendChild(btn); });
            container.appendChild(refRow);
        }

        // Duration
        container.appendChild(makeLabeledRow('Duration',
            makeNumberInput(c.duration || 1.5, function(v) {
                ensureCursor(step);
                var cursor = Object.assign({}, step.cursor, { duration: v });
                state.updateStep(idx, 'cursor', cursor);
            }, { step: '0.1', min: '0.1', style: { width: '72px' } })
        ));

        // Delay (NEW)
        container.appendChild(makeLabeledRow('Delay',
            makeNumberInput(c.delay || 0, function(v) {
                ensureCursor(step);
                var cursor = Object.assign({}, step.cursor, { delay: v || undefined });
                state.updateStep(idx, 'cursor', cursor);
            }, { step: '0.1', min: '0', style: { width: '72px' } })
        ));

        // Ease
        container.appendChild(makeLabeledRow('Ease',
            makeSelect([
                { value: 'power2.inOut' }, { value: 'power2.out' }, { value: 'power2.in' },
                { value: 'power3.inOut' }, { value: 'elastic.out' }, { value: 'back.out' },
                { value: 'none' },
            ], c.ease || 'power2.inOut', function(v) {
                ensureCursor(step);
                var cursor = Object.assign({}, step.cursor, { ease: v === 'power2.inOut' ? undefined : v });
                state.updateStep(idx, 'cursor', cursor);
            })
        ));

        // Checkboxes row
        container.appendChild(makeRow([
            makeCheckbox('click', !!c.click, function(v) {
                ensureCursor(step);
                var cursor = Object.assign({}, step.cursor, { click: v || undefined });
                state.updateStep(idx, 'cursor', cursor);
            }),
            makeCheckbox('loop', !!c.loop, function(v) {
                ensureCursor(step);
                var cursor = Object.assign({}, step.cursor, { loop: v || undefined });
                state.updateStep(idx, 'cursor', cursor);
            }),
        ]));

        // Image (NEW)
        container.appendChild(makeLabeledRow('Image',
            makeInput(c.image || '', function(v) {
                ensureCursor(step);
                var cursor = Object.assign({}, step.cursor, { image: v || undefined });
                state.updateStep(idx, 'cursor', cursor);
            }, { placeholder: 'cursor image URL' })
        ));

        // SVG Path
        container.appendChild(makeLabeledRow('svgPath',
            makeInput(c.svgPath || '', function(v) {
                ensureCursor(step);
                var cursor = Object.assign({}, step.cursor, { svgPath: v || undefined });
                state.updateStep(idx, 'cursor', cursor);
            }, { placeholder: 'M0,0 C...' })
        ));

        // --- Waypoints ---
        if (c.waypoints && c.waypoints.length) {
            container.appendChild(el('div', { style: { marginTop: '8px', marginBottom: '4px' } }, [
                el('span', { textContent: 'Waypoints', className: 'sb-label' }),
            ]));
            c.waypoints.forEach(function(wp, wi) {
                var wpRow = el('div', { style: { display: 'flex', gap: '4px', alignItems: 'center', marginBottom: '4px', fontSize: '11px' } });
                wpRow.appendChild(el('span', { textContent: (wi + 1) + '.', style: { color: '#7a7f8e', width: '16px' } }));
                wpRow.appendChild(el('span', { textContent: wp.target, className: 'sb-selector', style: { flex: '1' } }));

                var actionSel = makeSelect(
                    [{ value: 'path' }, { value: 'click' }, { value: 'hover' }],
                    wp.action || 'path',
                    (function(wIdx) { return function(v) {
                        var wps = step.cursor.waypoints.slice();
                        wps[wIdx] = Object.assign({}, wps[wIdx], { action: v === 'path' ? undefined : v });
                        var cursor = Object.assign({}, step.cursor, { waypoints: wps });
                        state.updateStep(idx, 'cursor', cursor);
                    }; })(wi)
                );
                actionSel.style.width = '70px';
                wpRow.appendChild(actionSel);

                var pauseInp = makeNumberInput(wp.pause || 0,
                    (function(wIdx) { return function(v) {
                        var wps = step.cursor.waypoints.slice();
                        wps[wIdx] = Object.assign({}, wps[wIdx], { pause: v || undefined });
                        var cursor = Object.assign({}, step.cursor, { waypoints: wps });
                        state.updateStep(idx, 'cursor', cursor);
                    }; })(wi),
                    { step: '0.5', min: '0' }
                );
                pauseInp.style.width = '50px';
                wpRow.appendChild(pauseInp);
                wpRow.appendChild(el('span', { textContent: 's', style: { color: '#7a7f8e' } }));

                // Remove waypoint button
                wpRow.appendChild(makeBtn('', 'sb-btn-ghost sb-btn-icon sb-btn-sm', (function(wIdx) { return function() {
                    var wps = step.cursor.waypoints.slice();
                    wps.splice(wIdx, 1);
                    var cursor = Object.assign({}, step.cursor, { waypoints: wps.length ? wps : undefined });
                    state.updateStep(idx, 'cursor', cursor);
                }; })(wi), 'close'));

                container.appendChild(wpRow);
            });
        }

        // Add waypoint button
        container.appendChild(makeRow([
            makeBtn('+ Waypoint', 'sb-btn-ghost sb-btn-sm', function() {
                events.emit('pick-element', { callback: function(sel) {
                    ensureCursor(step);
                    var wps = (step.cursor.waypoints || []).slice();
                    wps.push({ target: sel });
                    var cursor = Object.assign({}, step.cursor, { waypoints: wps });
                    state.updateStep(idx, 'cursor', cursor);
                }});
            }, 'add'),
        ]));
    },
});
