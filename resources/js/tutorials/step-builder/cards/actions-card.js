// cards/actions-card.js — NEW: Actions card (silentClick, linkNext, redirect)

import { registerCard } from '../ui/cards';
import { el, makeInput, makeLabeledRow, makeSelectorRow } from '../helpers';
import * as state from '../state';
import * as events from '../events';

registerCard({
    name: 'actions',
    icon: 'actions',
    title: 'Actions',
    defaultVisible: false,
    hasData: function(step) {
        return !!(step.silentClick || step.linkNext || step.redirect);
    },
    onDisable: function(step) {
        var idx = state.getSelectedIndex();
        state.updateStepBatch(idx, { silentClick: undefined, linkNext: undefined, redirect: undefined });
    },
    render: function(container, step) {
        var idx = state.getSelectedIndex();

        // silentClick
        container.appendChild(makeSelectorRow('silentClick', step.silentClick, 'yellow', function() {
            events.emit('pick-element', { callback: function(sel) {
                state.updateStep(idx, 'silentClick', sel);
            }});
        }, step.silentClick ? function() {
            state.updateStep(idx, 'silentClick', undefined);
        } : null));

        // linkNext
        container.appendChild(makeSelectorRow('linkNext', step.linkNext, 'primary', function() {
            events.emit('pick-element', { callback: function(sel) {
                state.updateStep(idx, 'linkNext', sel);
            }});
        }, step.linkNext ? function() {
            state.updateStep(idx, 'linkNext', undefined);
        } : null));

        // redirect
        container.appendChild(makeLabeledRow('redirect',
            makeInput(step.redirect || '', function(v) {
                state.updateStep(idx, 'redirect', v || undefined);
            }, { placeholder: '/path/to/page' })
        ));

        // Dev mode info
        container.appendChild(el('div', {
            textContent: 'In dev mode: silentClick shows badge instead of clicking, linkNext is disabled.',
            style: { fontSize: '10px', color: '#7a7f8e', marginTop: '4px' },
        }));
    },
});
