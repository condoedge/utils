// cards/highlight-card.js — Highlight card (groups, padding, borderRadius, blockOutside, multi-pick)

import { registerCard } from '../ui/cards';
import { el, makeBtn, makeInput, makeNumberInput, makeSelect, makeCheckbox, makeRow, makeLabeledRow } from '../helpers';
import { iconEl } from '../icons';
import * as state from '../state';
import * as events from '../events';

function ensureHighlight(step) {
    if (!step.highlight) step.highlight = { groups: [], padding: 8, borderRadius: 8 };
    if (!step.highlight.groups) step.highlight.groups = [];
}

function flattenSelectors(groups) {
    var selectors = [];
    (groups || []).forEach(function(g) {
        var elems = g.elements || g;
        if (Array.isArray(elems)) {
            elems.forEach(function(sel) { selectors.push(sel); });
        }
    });
    return selectors;
}

function rebuildGroups(step, selectors, mode, groupAssignments) {
    ensureHighlight(step);
    if (mode === 'together') {
        step.highlight.groups = selectors.length ? [selectors.slice()] : [];
    } else if (mode === 'separate') {
        step.highlight.groups = selectors.map(function(s) { return [s]; });
    } else if (mode === 'custom') {
        var gMap = {};
        selectors.forEach(function(sel, i) {
            var g = (groupAssignments && groupAssignments[i]) || 1;
            if (!gMap[g]) gMap[g] = [];
            gMap[g].push(sel);
        });
        step.highlight.groups = [];
        Object.keys(gMap).sort(function(a, b) { return a - b; }).forEach(function(k) {
            step.highlight.groups.push(gMap[k]);
        });
    }
}

registerCard({
    name: 'highlight',
    icon: 'highlight',
    title: 'Highlight',
    defaultVisible: true,
    hasData: function(step) {
        return !!(step.highlight && step.highlight.groups && step.highlight.groups.length);
    },
    onDisable: function(step) {
        state.updateStep(state.getSelectedIndex(), 'highlight', undefined);
    },
    render: function(container, step) {
        var idx = state.getSelectedIndex();
        var h = step.highlight || {};
        var groups = h.groups || [];
        var selectors = flattenSelectors(groups);
        var mode = h._mode || 'together';
        var groupAssignments = h._groups || selectors.map(function() { return 1; });

        // Selector list
        if (selectors.length) {
            var listDiv = el('div', { style: { marginBottom: '8px' } });
            selectors.forEach(function(sel, i) {
                var row = el('div', { style: { display: 'flex', alignItems: 'center', gap: '4px', padding: '3px 0' } });
                row.appendChild(el('span', { textContent: (i + 1) + '.', style: { color: '#7a7f8e', fontSize: '10px', width: '16px' } }));

                // Editable selector input
                var selInput = makeInput(sel, (function(sIdx) { return function(v) {
                    var sels = flattenSelectors(step.highlight.groups);
                    sels[sIdx] = v;
                    rebuildGroups(step, sels, step.highlight._mode || 'together', step.highlight._groups);
                    state.updateStep(idx, 'highlight', step.highlight);
                }; })(i), { style: { flex: '1', fontSize: '11px', fontFamily: '"Fira Code", monospace', padding: '4px 6px' } });
                row.appendChild(selInput);

                // Group number (custom mode)
                if (mode === 'custom') {
                    row.appendChild(el('span', { textContent: 'grp:', style: { color: '#7a7f8e', fontSize: '10px' } }));
                    var grpInp = makeNumberInput(groupAssignments[i] || 1,
                        (function(sIdx) { return function(v) {
                            ensureHighlight(step);
                            if (!step.highlight._groups) step.highlight._groups = selectors.map(function() { return 1; });
                            step.highlight._groups[sIdx] = Math.max(1, v);
                            var sels = flattenSelectors(step.highlight.groups);
                            rebuildGroups(step, sels, 'custom', step.highlight._groups);
                            state.updateStep(idx, 'highlight', step.highlight);
                        }; })(i),
                        { min: '1', step: '1' }
                    );
                    grpInp.style.width = '50px';
                    row.appendChild(grpInp);
                }

                // Remove button
                row.appendChild(makeBtn('', 'sb-btn-ghost sb-btn-icon sb-btn-sm', (function(sIdx) { return function() {
                    var sels = flattenSelectors(step.highlight.groups);
                    sels.splice(sIdx, 1);
                    if (step.highlight._groups) step.highlight._groups.splice(sIdx, 1);
                    rebuildGroups(step, sels, step.highlight._mode || 'together', step.highlight._groups);
                    state.updateStep(idx, 'highlight', sels.length ? step.highlight : undefined);
                }; })(i), 'close'));

                listDiv.appendChild(row);
            });
            container.appendChild(listDiv);
        }

        // Mode selector
        container.appendChild(makeRow([
            el('label', { textContent: 'Mode', className: 'sb-label' }),
            makeSelect(
                [{ value: 'together' }, { value: 'separate' }, { value: 'custom', label: 'Custom groups' }],
                mode,
                function(v) {
                    ensureHighlight(step);
                    step.highlight._mode = v;
                    var sels = flattenSelectors(step.highlight.groups);
                    rebuildGroups(step, sels, v, step.highlight._groups);
                    state.updateStep(idx, 'highlight', step.highlight);
                }
            ),
        ]));

        // Padding (NEW)
        container.appendChild(makeLabeledRow('Padding',
            makeNumberInput(h.padding || 8, function(v) {
                ensureHighlight(step);
                step.highlight.padding = v;
                state.updateStep(idx, 'highlight', step.highlight);
            }, { min: '0', step: '1', style: { width: '72px' } })
        ));

        // Border Radius (NEW)
        container.appendChild(makeLabeledRow('Radius',
            makeNumberInput(h.borderRadius || 8, function(v) {
                ensureHighlight(step);
                step.highlight.borderRadius = v;
                state.updateStep(idx, 'highlight', step.highlight);
            }, { min: '0', step: '1', style: { width: '72px' } })
        ));

        // blockOutside checkbox
        container.appendChild(makeRow([
            makeCheckbox('blockOutside', !!h.blockOutside, function(v) {
                ensureHighlight(step);
                step.highlight.blockOutside = v || undefined;
                state.updateStep(idx, 'highlight', step.highlight);
            }),
        ]));

        // Pick / Clear buttons
        container.appendChild(makeRow([
            makeBtn('+ Pick Element', 'sb-btn-yellow sb-btn-sm', function() {
                events.emit('pick-element', { callback: function(sel) {
                    ensureHighlight(step);
                    var sels = flattenSelectors(step.highlight.groups);
                    sels.push(sel);
                    if (!step.highlight._groups) step.highlight._groups = [];
                    step.highlight._groups.push(1);
                    rebuildGroups(step, sels, step.highlight._mode || 'together', step.highlight._groups);
                    state.updateStep(idx, 'highlight', step.highlight);
                }});
            }, 'pick'),
            selectors.length ? makeBtn('Clear All', 'sb-btn-ghost sb-btn-sm', function() {
                state.updateStep(idx, 'highlight', undefined);
            }, 'trash') : null,
        ]));
    },
});
