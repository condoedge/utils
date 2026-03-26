// cards/scroll-card.js — Scroll card (scrollTo, scrollInside with mode/amount/duration)

import { registerCard } from '../ui/cards';
import { el, makeBtn, makeInput, makeNumberInput, makeSelect, makeRow, makeLabeledRow, makeSelectorRow } from '../helpers';
import * as state from '../state';
import * as events from '../events';

registerCard({
    name: 'scroll',
    icon: 'scroll',
    title: 'Scroll',
    defaultVisible: false,
    hasData: function(step) {
        return !!(step.scrollTo || (step.scrollInside && step.scrollInside.selector));
    },
    onDisable: function(step) {
        var idx = state.getSelectedIndex();
        state.updateStepBatch(idx, { scrollTo: undefined, scrollInside: undefined });
    },
    render: function(container, step) {
        var idx = state.getSelectedIndex();

        // scrollTo
        container.appendChild(makeSelectorRow('scrollTo', step.scrollTo, 'primary', function() {
            events.emit('pick-element', { callback: function(sel) {
                state.updateStep(idx, 'scrollTo', sel);
            }});
        }, step.scrollTo ? function() {
            state.updateStep(idx, 'scrollTo', undefined);
        } : null));

        // scrollInside
        var si = step.scrollInside || {};
        container.appendChild(el('div', { style: { marginTop: '8px', marginBottom: '4px' } }, [
            el('span', { textContent: 'scrollInside', className: 'sb-label' }),
        ]));

        container.appendChild(makeSelectorRow('Selector', si.selector, 'green', function() {
            events.emit('pick-element', { callback: function(sel) {
                var scrollInside = Object.assign({}, step.scrollInside || {}, { selector: sel });
                state.updateStep(idx, 'scrollInside', scrollInside);
            }});
        }, si.selector ? function() {
            state.updateStep(idx, 'scrollInside', undefined);
        } : null));

        if (si.selector) {
            // Mode: to or by
            var hasTo = si.to !== undefined;
            container.appendChild(makeRow([
                el('label', { textContent: 'Mode', className: 'sb-label' }),
                makeSelect(
                    [{ value: 'to', label: 'Scroll to' }, { value: 'by', label: 'Scroll by' }],
                    hasTo ? 'to' : 'by',
                    function(v) {
                        var scrollInside = Object.assign({}, step.scrollInside);
                        if (v === 'to') { scrollInside.to = scrollInside.by || 0; delete scrollInside.by; }
                        else { scrollInside.by = scrollInside.to || 0; delete scrollInside.to; }
                        state.updateStep(idx, 'scrollInside', scrollInside);
                    }
                ),
            ]));

            // Amount
            container.appendChild(makeLabeledRow('Amount',
                makeNumberInput(hasTo ? si.to : (si.by || 0), function(v) {
                    var scrollInside = Object.assign({}, step.scrollInside);
                    if (hasTo) scrollInside.to = v; else scrollInside.by = v;
                    state.updateStep(idx, 'scrollInside', scrollInside);
                }, { step: '10' })
            ));

            // Duration
            container.appendChild(makeLabeledRow('Duration',
                makeNumberInput(si.duration || 0.5, function(v) {
                    var scrollInside = Object.assign({}, step.scrollInside, { duration: v || undefined });
                    state.updateStep(idx, 'scrollInside', scrollInside);
                }, { step: '0.1', min: '0.1' })
            ));
        }
    },
});
