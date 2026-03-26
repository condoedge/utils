// cards/conditions-card.js — NEW: Conditions card (showIf, hideIf)

import { registerCard } from '../ui/cards';
import { el, makeSelectorRow } from '../helpers';
import * as state from '../state';
import * as events from '../events';

registerCard({
    name: 'conditions',
    icon: 'conditions',
    title: 'Conditions',
    defaultVisible: true,
    hasData: function(step) {
        return !!(step.showIf || step.hideIf);
    },
    onDisable: function(step) {
        var idx = state.getSelectedIndex();
        state.updateStepBatch(idx, { showIf: undefined, hideIf: undefined });
    },
    render: function(container, step) {
        var idx = state.getSelectedIndex();

        // showIf
        container.appendChild(makeSelectorRow('showIf', step.showIf, 'green', function() {
            events.emit('pick-element', { callback: function(sel) {
                state.updateStep(idx, 'showIf', sel);
            }});
        }, step.showIf ? function() {
            state.updateStep(idx, 'showIf', undefined);
        } : null));

        // hideIf
        container.appendChild(makeSelectorRow('hideIf', step.hideIf, 'red', function() {
            events.emit('pick-element', { callback: function(sel) {
                state.updateStep(idx, 'hideIf', sel);
            }});
        }, step.hideIf ? function() {
            state.updateStep(idx, 'hideIf', undefined);
        } : null));

        container.appendChild(el('div', {
            textContent: 'Step is shown/hidden based on element visibility in the DOM.',
            style: { fontSize: '10px', color: '#7a7f8e', marginTop: '4px' },
        }));
    },
});
