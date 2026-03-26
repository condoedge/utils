// cards/options-card.js — Options card (label, action type, params, add/remove)

import { registerCard } from '../ui/cards';
import { el, makeBtn, makeInput, makeNumberInput, makeCheckbox } from '../helpers';
import { iconEl } from '../icons';
import * as state from '../state';

var ACTION_TYPES = [
    { value: 'done', label: 'Done', color: '#2ecc71', icon: 'advance' },
    { value: 'redirect', label: 'Redirect', color: '#6c8aff', icon: 'linkNext' },
    { value: 'goToStep', label: 'Go to', color: '#f1c40f', icon: 'actions' },
];

function getActionType(opt) {
    if (opt.done) return 'done';
    if (opt.redirect !== undefined) return 'redirect';
    if (opt.goToStep !== undefined) return 'goToStep';
    return 'done';
}

function makeActionPill(type, isActive, onClick) {
    var cfg = ACTION_TYPES.filter(function(a) { return a.value === type; })[0];
    var pill = el('div', { style: {
        display: 'inline-flex', alignItems: 'center', gap: '3px',
        padding: '3px 8px', borderRadius: '12px', cursor: 'pointer',
        fontSize: '10px', fontWeight: '600', transition: 'all 0.15s',
        background: isActive ? cfg.color : 'rgba(255,255,255,0.04)',
        color: isActive ? '#fff' : '#7a7f8e',
        border: isActive ? 'none' : '1px solid rgba(255,255,255,0.08)',
    }});
    pill.appendChild(iconEl(cfg.icon, 10));
    pill.appendChild(document.createTextNode(cfg.label));
    pill.addEventListener('click', function(e) { e.stopPropagation(); onClick(); });
    return pill;
}

registerCard({
    name: 'options',
    icon: 'options',
    title: 'Options',
    defaultVisible: false,
    hasData: function(step) {
        return !!(step.options && step.options.length);
    },
    onDisable: function(step) {
        state.updateStep(state.getSelectedIndex(), 'options', undefined);
    },
    render: function(container, step) {
        var idx = state.getSelectedIndex();
        var opts = step.options || [];

        opts.forEach(function(opt, i) {
            var actionType = getActionType(opt);
            var cfg = ACTION_TYPES.filter(function(a) { return a.value === actionType; })[0];

            // Card wrapper
            var card = el('div', { style: {
                background: 'rgba(0,0,0,0.15)', borderRadius: '8px', marginBottom: '6px',
                borderLeft: '3px solid ' + cfg.color, overflow: 'hidden',
            }});

            // Top row: number + label input + delete
            var topRow = el('div', { style: {
                display: 'flex', gap: '6px', alignItems: 'center', padding: '8px 10px 4px',
            }});
            var numBadge = el('div', { style: {
                width: '20px', height: '20px', borderRadius: '50%', display: 'flex',
                alignItems: 'center', justifyContent: 'center',
                background: cfg.color, color: '#fff',
                fontSize: '10px', fontWeight: '700', flexShrink: '0',
            }});
            numBadge.textContent = i + 1;
            topRow.appendChild(numBadge);

            var labelInp = makeInput(opt.label || '', (function(oIdx) { return function(v) {
                var options = step.options.slice();
                options[oIdx] = Object.assign({}, options[oIdx], { label: v });
                state.updateStep(idx, 'options', options);
            }; })(i), { placeholder: 'Button label...', style: { flex: '1', padding: '4px 8px', fontSize: '12px' } });
            topRow.appendChild(labelInp);

            var delBtn = el('div', { style: {
                width: '20px', height: '20px', display: 'flex', alignItems: 'center',
                justifyContent: 'center', cursor: 'pointer', borderRadius: '4px',
                color: '#7a7f8e', transition: 'all 0.15s', flexShrink: '0',
            }});
            delBtn.appendChild(iconEl('close', 12));
            delBtn.addEventListener('mouseenter', function() { delBtn.style.color = '#e74c3c'; delBtn.style.background = 'rgba(231,76,60,0.1)'; });
            delBtn.addEventListener('mouseleave', function() { delBtn.style.color = '#7a7f8e'; delBtn.style.background = 'transparent'; });
            (function(oIdx) {
                delBtn.addEventListener('click', function() {
                    var options = step.options.slice();
                    options.splice(oIdx, 1);
                    state.updateStep(idx, 'options', options.length ? options : undefined);
                });
            })(i);
            topRow.appendChild(delBtn);
            card.appendChild(topRow);

            // Bottom row: action type pills + params
            var bottomRow = el('div', { style: {
                display: 'flex', gap: '4px', alignItems: 'center', padding: '4px 10px 8px',
                flexWrap: 'wrap',
            }});

            ACTION_TYPES.forEach(function(at) {
                bottomRow.appendChild(makeActionPill(at.value, actionType === at.value, (function(oIdx, atValue) { return function() {
                    var options = step.options.slice();
                    var newOpt = { label: options[oIdx].label };
                    if (atValue === 'done') newOpt.done = true;
                    else if (atValue === 'redirect') newOpt.redirect = '';
                    else if (atValue === 'goToStep') newOpt.goToStep = 0;
                    options[oIdx] = newOpt;
                    state.updateStep(idx, 'options', options);
                }; })(i, at.value)));
            });

            // Action params inline
            if (actionType === 'redirect') {
                var sep = el('div', { style: { width: '1px', height: '16px', background: 'rgba(255,255,255,0.1)', margin: '0 2px' } });
                bottomRow.appendChild(sep);
                var pathInp = makeInput(opt.redirect || '', (function(oIdx) { return function(v) {
                    var options = step.options.slice();
                    options[oIdx] = Object.assign({}, options[oIdx], { redirect: v });
                    state.updateStep(idx, 'options', options);
                }; })(i), { placeholder: '/path', style: { width: '100px', padding: '3px 6px', fontSize: '11px' } });
                bottomRow.appendChild(pathInp);

                bottomRow.appendChild(makeCheckbox('startTutorial', !!opt.startTutorial, (function(oIdx) { return function(v) {
                    var options = step.options.slice();
                    options[oIdx] = Object.assign({}, options[oIdx], { startTutorial: v || undefined });
                    state.updateStep(idx, 'options', options);
                }; })(i)));

            } else if (actionType === 'goToStep') {
                var sep = el('div', { style: { width: '1px', height: '16px', background: 'rgba(255,255,255,0.1)', margin: '0 2px' } });
                bottomRow.appendChild(sep);
                bottomRow.appendChild(el('span', { textContent: '#', style: { color: '#7a7f8e', fontSize: '11px' } }));
                var stepInp = makeNumberInput(opt.goToStep || 0, (function(oIdx) { return function(v) {
                    var options = step.options.slice();
                    options[oIdx] = Object.assign({}, options[oIdx], { goToStep: v });
                    state.updateStep(idx, 'options', options);
                }; })(i), { min: '0', step: '1' });
                stepInp.style.width = '50px';
                stepInp.style.padding = '3px 6px';
                stepInp.style.fontSize = '11px';
                stepInp.style.textAlign = 'center';
                bottomRow.appendChild(stepInp);
            }

            card.appendChild(bottomRow);
            container.appendChild(card);
        });

        // Add button
        var addBtn = makeBtn('+ Add Option', 'sb-btn-green sb-btn-sm', function() {
            var options = (step.options || []).slice();
            options.push({ label: '', done: true });
            state.updateStep(idx, 'options', options);
        }, 'add');
        addBtn.style.width = '100%';
        addBtn.style.justifyContent = 'center';
        addBtn.style.marginTop = '4px';
        container.appendChild(addBtn);
    },
});
