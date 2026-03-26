// cards/hover-card.js — Hover card (selectors, preview, parent walk-up)

import { registerCard } from '../ui/cards';
import { el, makeBtn, makeInput, makeRow } from '../helpers';
import * as state from '../state';
import * as events from '../events';

registerCard({
    name: 'hover',
    icon: 'hover',
    title: 'Hover',
    defaultVisible: false,
    hasData: function(step) {
        return !!step.hover;
    },
    onDisable: function(step) {
        state.updateStep(state.getSelectedIndex(), 'hover', undefined);
    },
    render: function(container, step) {
        var idx = state.getSelectedIndex();
        var hovers = step.hover ? (Array.isArray(step.hover) ? step.hover : [step.hover]) : [];

        // Selector list
        if (hovers.length) {
            hovers.forEach(function(sel, i) {
                var row = el('div', { style: { display: 'flex', alignItems: 'center', gap: '4px', marginBottom: '4px' } });
                row.appendChild(el('span', { textContent: (i + 1) + '.', style: { color: '#7a7f8e', fontSize: '10px', width: '16px' } }));

                var inp = makeInput(sel, (function(hIdx) { return function(v) {
                    var list = (Array.isArray(step.hover) ? step.hover : [step.hover]).slice();
                    list[hIdx] = v;
                    state.updateStep(idx, 'hover', list.length === 1 ? list[0] : list);
                }; })(i), { style: { flex: '1', fontSize: '11px', fontFamily: '"Fira Code", monospace', padding: '4px 6px' } });
                row.appendChild(inp);

                row.appendChild(makeBtn('', 'sb-btn-ghost sb-btn-icon sb-btn-sm', (function(hIdx) { return function() {
                    var list = (Array.isArray(step.hover) ? step.hover : [step.hover]).slice();
                    list.splice(hIdx, 1);
                    state.updateStep(idx, 'hover', list.length === 0 ? undefined : list.length === 1 ? list[0] : list);
                }; })(i), 'close'));

                container.appendChild(row);
            });
        }

        // Buttons
        container.appendChild(makeRow([
            makeBtn('+ Pick', 'sb-btn-yellow sb-btn-sm', function() {
                events.emit('pick-element', { callback: function(sel) {
                    var list = step.hover ? (Array.isArray(step.hover) ? step.hover.slice() : [step.hover]) : [];
                    list.push(sel);
                    state.updateStep(idx, 'hover', list.length === 1 ? list[0] : list);
                }});
            }, 'pick'),
            hovers.length ? makeBtn('Clear', 'sb-btn-ghost sb-btn-sm', function() {
                state.updateStep(idx, 'hover', undefined);
            }, 'trash') : null,
        ]));
    },
});
