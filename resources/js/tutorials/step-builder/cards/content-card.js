// cards/content-card.js — Content card (html key, FR, EN, branch, preview, toggles)
// Always visible, no toggle

import { registerCard } from '../ui/cards';
import { el, makeInput } from '../helpers';
import { iconEl } from '../icons';
import * as state from '../state';

function makeField(icon, label, value, placeholder, onChange, mono) {
    var row = el('div', { style: {
        display: 'flex', alignItems: 'center', gap: '6px', marginBottom: '6px',
    }});
    var labelEl = el('div', { style: {
        display: 'flex', alignItems: 'center', gap: '4px', minWidth: '52px',
    }});
    labelEl.appendChild(iconEl(icon, 12));
    labelEl.appendChild(el('span', { textContent: label, style: { fontSize: '10px', color: '#7a7f8e', fontWeight: '600', textTransform: 'uppercase', letterSpacing: '0.3px' } }));
    row.appendChild(labelEl);

    var inp = makeInput(value || '', onChange, { placeholder: placeholder });
    inp.style.flex = '1';
    inp.style.padding = '5px 8px';
    inp.style.fontSize = '12px';
    if (mono) {
        inp.style.fontFamily = '"Fira Code", "Cascadia Code", monospace';
        inp.style.fontSize = '11px';
        inp.style.color = '#2ecc71';
    }
    row.appendChild(inp);
    return row;
}

function makeTogglePill(label, active, onChange) {
    var pill = el('div', { style: {
        display: 'inline-flex', alignItems: 'center', gap: '6px',
        padding: '4px 10px', borderRadius: '20px', cursor: 'pointer',
        fontSize: '11px', fontWeight: '500', transition: 'all 0.15s',
        background: active ? 'rgba(108,138,255,0.2)' : 'rgba(255,255,255,0.04)',
        color: active ? '#6c8aff' : '#7a7f8e',
        border: active ? '1px solid rgba(108,138,255,0.3)' : '1px solid rgba(255,255,255,0.08)',
    }});
    var dot = el('div', { style: {
        width: '8px', height: '8px', borderRadius: '50%',
        background: active ? '#6c8aff' : 'rgba(255,255,255,0.15)',
        transition: 'all 0.15s',
    }});
    pill.appendChild(dot);
    pill.appendChild(document.createTextNode(label));
    pill.addEventListener('click', function() { onChange(!active); });
    return pill;
}

registerCard({
    name: 'content',
    icon: 'content',
    title: 'Content',
    alwaysVisible: true,
    hasData: function() { return true; },
    render: function(container, step) {
        var idx = state.getSelectedIndex();

        // Key (translation key)
        container.appendChild(makeField('content', 'Key', step.html, 'tutorial.section.key', function(v) {
            state.updateStep(idx, 'html', v);
        }, true));

        // FR / EN in a compact group
        var langGroup = el('div', { style: {
            padding: '6px 8px', background: 'rgba(255,255,255,0.02)',
            borderRadius: '6px', border: '1px solid rgba(255,255,255,0.04)',
            marginBottom: '6px',
        }});
        var langTitle = el('div', { style: { display: 'flex', alignItems: 'center', gap: '4px', marginBottom: '6px' } });
        langTitle.appendChild(el('span', { textContent: '🌐', style: { fontSize: '10px' } }));
        langTitle.appendChild(el('span', { textContent: 'Translations (preview only)', style: { fontSize: '9px', color: '#7a7f8e', fontStyle: 'italic' } }));
        langGroup.appendChild(langTitle);

        var frRow = el('div', { style: { display: 'flex', alignItems: 'center', gap: '6px', marginBottom: '4px' } });
        frRow.appendChild(el('span', { textContent: 'FR', style: { fontSize: '10px', fontWeight: '700', color: '#6c8aff', minWidth: '20px' } }));
        var frInp = makeInput(step._textFr || '', function(v) { state.updateStepDirect(idx, '_textFr', v); }, { placeholder: 'Texte français...' });
        frInp.style.flex = '1'; frInp.style.padding = '4px 8px'; frInp.style.fontSize = '11px';
        frRow.appendChild(frInp);
        langGroup.appendChild(frRow);

        var enRow = el('div', { style: { display: 'flex', alignItems: 'center', gap: '6px' } });
        enRow.appendChild(el('span', { textContent: 'EN', style: { fontSize: '10px', fontWeight: '700', color: '#2ecc71', minWidth: '20px' } }));
        var enInp = makeInput(step._textEn || '', function(v) { state.updateStepDirect(idx, '_textEn', v); }, { placeholder: 'English text...' });
        enInp.style.flex = '1'; enInp.style.padding = '4px 8px'; enInp.style.fontSize = '11px';
        enRow.appendChild(enInp);
        langGroup.appendChild(enRow);

        container.appendChild(langGroup);

        // Branch
        container.appendChild(makeField('graph', 'Branch', step._branch, 'main', function(v) {
            state.updateStep(idx, '_branch', v || undefined);
        }, false));

        // Mini-preview
        var previewText = step._textFr || step._textEn || step.html || '';
        if (previewText) {
            var preview = el('div', { style: {
                padding: '8px 10px', borderRadius: '8px',
                background: 'linear-gradient(135deg, rgba(255,255,255,0.04), rgba(255,255,255,0.02))',
                border: '1px solid rgba(255,255,255,0.06)',
                fontSize: '12px', color: '#b0b4c0', lineHeight: '1.5',
                marginBottom: '8px',
            }});
            preview.textContent = previewText;
            container.appendChild(preview);
        }

        // Toggles row
        var toggles = el('div', { style: { display: 'flex', gap: '6px', flexWrap: 'wrap' } });
        toggles.appendChild(makeTogglePill('overlay', step.overlay !== false, function(v) {
            state.updateStep(idx, 'overlay', v ? true : false);
        }));
        toggles.appendChild(makeTogglePill('showBack', !!step.showBack, function(v) {
            state.updateStep(idx, 'showBack', v ? true : undefined);
        }));
        container.appendChild(toggles);
    },
});
