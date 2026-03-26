// cards/advance-card.js — Advance card (click/auto/afterAnimation, delay)

import { registerCard } from '../ui/cards';
import { el, makeNumberInput } from '../helpers';
import { iconEl } from '../icons';
import * as state from '../state';

function getAdvanceMode(step) {
    if (step.autoNext !== undefined) return 'auto';
    if (step.afterAnimation) return 'afterAnimation';
    return 'click';
}

var MODE_CONFIG = [
    { value: 'click', label: 'Click', desc: 'Manual Next button', icon: 'advance', color: '#6c8aff' },
    { value: 'auto', label: 'Auto', desc: 'Advance after delay', icon: 'autoNext', color: '#2ecc71' },
    { value: 'afterAnimation', label: 'After Anim', desc: 'Wait for cursor to finish', icon: 'cursor', color: '#f1c40f' },
];

registerCard({
    name: 'advance',
    icon: 'advance',
    title: 'Advance',
    defaultVisible: false,
    hasData: function(step) {
        return step.autoNext !== undefined || !!step.afterAnimation;
    },
    render: function(container, step) {
        var idx = state.getSelectedIndex();
        var mode = getAdvanceMode(step);

        // Segmented control
        var segmented = el('div', { style: {
            display: 'flex', gap: '4px', marginBottom: '10px',
        }});

        MODE_CONFIG.forEach(function(m) {
            var isActive = mode === m.value;
            var btn = el('div', { style: {
                flex: '1', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '4px',
                padding: '8px 4px', borderRadius: '8px', cursor: 'pointer',
                background: isActive ? 'rgba(108,138,255,0.15)' : 'rgba(255,255,255,0.03)',
                border: isActive ? '1px solid ' + m.color : '1px solid rgba(255,255,255,0.06)',
                transition: 'all 0.15s',
            }});

            var iconWrap = el('div', { style: {
                width: '28px', height: '28px', borderRadius: '50%', display: 'flex',
                alignItems: 'center', justifyContent: 'center',
                background: isActive ? m.color : 'rgba(255,255,255,0.08)',
                color: isActive ? '#fff' : '#7a7f8e',
                transition: 'all 0.15s',
            }});
            iconWrap.appendChild(iconEl(m.icon, 14));
            btn.appendChild(iconWrap);

            btn.appendChild(el('span', {
                textContent: m.label,
                style: { fontSize: '11px', fontWeight: '600', color: isActive ? '#e0e4ec' : '#7a7f8e' },
            }));

            btn.addEventListener('mouseenter', function() {
                if (!isActive) btn.style.background = 'rgba(255,255,255,0.06)';
            });
            btn.addEventListener('mouseleave', function() {
                if (!isActive) btn.style.background = 'rgba(255,255,255,0.03)';
            });

            btn.addEventListener('click', function() {
                if (m.value === 'click') {
                    state.updateStepBatch(idx, { autoNext: undefined, afterAnimation: undefined });
                } else if (m.value === 'auto') {
                    state.updateStepBatch(idx, { autoNext: 3, afterAnimation: undefined });
                } else if (m.value === 'afterAnimation') {
                    state.updateStepBatch(idx, { autoNext: undefined, afterAnimation: true });
                }
            });

            segmented.appendChild(btn);
        });

        container.appendChild(segmented);

        // Description
        var activeConfig = MODE_CONFIG.filter(function(m) { return m.value === mode; })[0];
        container.appendChild(el('div', {
            textContent: activeConfig.desc,
            style: { fontSize: '11px', color: '#7a7f8e', marginBottom: '8px', textAlign: 'center' },
        }));

        // Auto delay input
        if (mode === 'auto') {
            var delayRow = el('div', { style: {
                display: 'flex', alignItems: 'center', gap: '8px',
                padding: '8px 10px', background: 'rgba(46,204,113,0.08)',
                borderRadius: '8px', border: '1px solid rgba(46,204,113,0.15)',
            }});
            delayRow.appendChild(iconEl('autoNext', 14));
            delayRow.appendChild(el('span', { textContent: 'Delay', style: { fontSize: '12px', color: '#b0b4c0', flex: '1' } }));
            var delayInput = makeNumberInput(typeof step.autoNext === 'number' ? step.autoNext : 3, function(v) {
                state.updateStep(idx, 'autoNext', Math.max(0.5, v));
            }, { step: '0.5', min: '0.5' });
            delayInput.style.width = '64px';
            delayInput.style.textAlign = 'center';
            delayRow.appendChild(delayInput);
            delayRow.appendChild(el('span', { textContent: 's', style: { fontSize: '11px', color: '#7a7f8e' } }));
            container.appendChild(delayRow);
        }
    },
});
