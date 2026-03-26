// cards/global-opts-card.js — Global Options card (bubble sizes, fontSize) + Code Output

import { registerCard } from '../ui/cards';
import { el, makeInput, makeBtn, copyToClipboard, flashStatus } from '../helpers';
import { iconEl } from '../icons';
import * as state from '../state';
import * as codegen from '../codegen';

var _exportView = 'current'; // 'current' | 'changed' | 'all'

function detectIntroFileName() {
    // Look for the inline script tag that loaded this tutorial
    // HasIntroAnimation injects the script content inline, but we can detect from the page's Kompo component
    var scripts = document.querySelectorAll('script');
    for (var i = 0; i < scripts.length; i++) {
        var text = scripts[i].textContent || '';
        if (text.indexOf('TutorialEngine.start') !== -1 || text.indexOf('TutorialEngine') !== -1) {
            // Try to find the script's source attribute or detect from data attributes
            var src = scripts[i].getAttribute('src') || '';
            var match = src.match(/intro-([a-z0-9-]+)\.js/);
            if (match) return 'intro-' + match[1];
        }
    }
    // Fallback: try to detect from the page URL path
    // e.g. /members -> intro-members-list, /dashboard -> intro-dashboard-view
    // Or from a data attribute on the Kompo component
    var kompoEl = document.querySelector('[wire\\:id], [vl-component]');
    if (kompoEl) {
        var componentName = kompoEl.getAttribute('vl-component') || '';
        if (componentName) {
            // Convert "MembersList" -> "members-list"
            var slug = componentName.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase();
            return 'intro-' + slug;
        }
    }
    // Last fallback: prompt user? For now, use the page pathname
    var path = window.location.pathname.replace(/^\//, '').replace(/\//g, '-') || 'unknown';
    return 'intro-' + path;
}

function getExportText() {
    if (_exportView === 'all') return codegen.generateCopyAllText();
    if (_exportView === 'changed') return codegen.generateCopyChangedText();
    return codegen.generateCopyStepText(state.getSelectedIndex());
}

function updateCodePreview(container) {
    container.innerHTML = '';
    var idx = state.getSelectedIndex();

    // Info line
    var info = el('div', { style: {
        display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '4px',
    }});

    if (_exportView === 'current') {
        info.appendChild(el('span', { textContent: 'Step ' + idx, style: { fontSize: '10px', color: '#6c8aff', fontWeight: '600' } }));
        var changes = state.getStepChanges(idx);
        if (changes.length) {
            info.appendChild(el('span', { textContent: changes.length + ' change' + (changes.length > 1 ? 's' : ''), style: { fontSize: '10px', color: '#f1c40f' } }));
        }
    } else if (_exportView === 'changed') {
        var changedCount = 0;
        state.getSteps().forEach(function(s, i) { if (state.getStepChanges(i).length) changedCount++; });
        info.appendChild(el('span', { textContent: changedCount + ' step' + (changedCount !== 1 ? 's' : '') + ' modified', style: { fontSize: '10px', color: '#f1c40f', fontWeight: '600' } }));
    } else {
        info.appendChild(el('span', { textContent: state.getSteps().length + ' steps total', style: { fontSize: '10px', color: '#2ecc71', fontWeight: '600' } }));
    }
    container.appendChild(info);

    // Code block
    var text = _exportView === 'current'
        ? codegen.generateStepCode(state.getCurrentStep())
        : _exportView === 'changed'
            ? codegen.generateCopyChangedText()
            : codegen.generateFullCode();

    var code = el('pre', { className: 'sb-code', textContent: text });
    container.appendChild(code);
}

var DEFAULTS = {
    bubbleMaxWidth: 'clamp(260px, 85vw, 550px)',
    bubbleMinWidth: 'clamp(200px, 70vw, 350px)',
    bubbleFontSize: 'clamp(14px, 3.5vw, 16px)',
};

function makeOptInput(label, desc, value, defaultVal, onChange) {
    var wrapper = el('div', { style: { marginBottom: '8px' } });

    var headerRow = el('div', { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '3px' } });
    headerRow.appendChild(el('span', { textContent: label, style: { fontSize: '11px', color: '#b0b4c0', fontWeight: '600' } }));

    var isDefault = !value || value === defaultVal;
    if (!isDefault) {
        var resetBtn = el('span', { textContent: 'reset', style: {
            fontSize: '9px', color: '#6c8aff', cursor: 'pointer', textTransform: 'uppercase',
            letterSpacing: '0.3px', padding: '1px 4px', borderRadius: '3px',
            background: 'rgba(108,138,255,0.1)',
        }});
        resetBtn.addEventListener('click', function() { onChange(defaultVal); });
        headerRow.appendChild(resetBtn);
    }
    wrapper.appendChild(headerRow);

    var inp = makeInput(value || defaultVal, onChange, { placeholder: defaultVal });
    inp.style.padding = '5px 8px';
    inp.style.fontSize = '12px';
    if (isDefault) inp.style.color = '#7a7f8e';
    inp.addEventListener('focus', function() { inp.style.color = '#e0e4ec'; });
    wrapper.appendChild(inp);

    wrapper.appendChild(el('div', { textContent: desc, style: { fontSize: '9px', color: '#555', marginTop: '2px' } }));

    return wrapper;
}

registerCard({
    name: 'globalOpts',
    icon: 'settings',
    title: 'Global Options',
    defaultVisible: true,
    hasData: function() { return true; },
    render: function(container) {
        var ctx = state.getCtx();
        if (!ctx) return;

        // Bubble settings group
        var settingsGroup = el('div', { style: {
            padding: '8px 10px', background: 'rgba(255,255,255,0.02)',
            borderRadius: '8px', border: '1px solid rgba(255,255,255,0.04)',
            marginBottom: '10px',
        }});
        var settingsTitle = el('div', { style: {
            display: 'flex', alignItems: 'center', gap: '6px', marginBottom: '8px',
        }});
        settingsTitle.appendChild(iconEl('content', 12));
        settingsTitle.appendChild(el('span', { textContent: 'Bubble Style', style: { fontSize: '10px', color: '#7a7f8e', textTransform: 'uppercase', letterSpacing: '0.3px', fontWeight: '600' } }));
        settingsGroup.appendChild(settingsTitle);

        settingsGroup.appendChild(makeOptInput('Max Width', 'Largeur maximale de la bulle (CSS)', ctx.opts.bubbleMaxWidth, DEFAULTS.bubbleMaxWidth, function(v) {
            ctx.opts.bubbleMaxWidth = v;
            state.forceRefresh();
        }));
        settingsGroup.appendChild(makeOptInput('Min Width', 'Largeur minimale de la bulle (CSS)', ctx.opts.bubbleMinWidth, DEFAULTS.bubbleMinWidth, function(v) {
            ctx.opts.bubbleMinWidth = v;
            state.forceRefresh();
        }));
        settingsGroup.appendChild(makeOptInput('Font Size', 'Taille du texte dans la bulle (CSS)', ctx.opts.bubbleFontSize, DEFAULTS.bubbleFontSize, function(v) {
            ctx.opts.bubbleFontSize = v;
            state.forceRefresh();
        }));

        container.appendChild(settingsGroup);

        // Code output group
        var codeGroup = el('div', { style: {
            padding: '8px 10px', background: 'rgba(0,0,0,0.15)',
            borderRadius: '8px', border: '1px solid rgba(255,255,255,0.04)',
        }});
        var codeTitle = el('div', { style: {
            display: 'flex', alignItems: 'center', gap: '6px', marginBottom: '8px',
        }});
        codeTitle.appendChild(iconEl('copy', 12));
        codeTitle.appendChild(el('span', { textContent: 'Export', style: { fontSize: '10px', color: '#7a7f8e', textTransform: 'uppercase', letterSpacing: '0.3px', fontWeight: '600' } }));
        codeGroup.appendChild(codeTitle);

        // View mode selector
        var viewModes = [
            { value: 'current', label: 'Current Step' },
            { value: 'changed', label: 'Changed' },
            { value: 'all', label: 'All' },
        ];
        var viewRow = el('div', { style: {
            display: 'flex', gap: '2px', background: 'rgba(0,0,0,0.2)', borderRadius: '8px',
            padding: '2px', marginBottom: '8px',
        }});
        viewModes.forEach(function(vm) {
            var isActive = vm.value === _exportView;
            var tab = el('div', { style: {
                flex: '1', textAlign: 'center', padding: '5px 4px', borderRadius: '6px',
                cursor: 'pointer', fontSize: '11px', fontWeight: '600', transition: 'all 0.15s',
                background: isActive ? 'rgba(108,138,255,0.25)' : 'transparent',
                color: isActive ? '#6c8aff' : '#7a7f8e',
                border: isActive ? '1px solid rgba(108,138,255,0.3)' : '1px solid transparent',
            }});
            tab.textContent = vm.label;
            tab.addEventListener('mouseenter', function() { if (!isActive) tab.style.background = 'rgba(255,255,255,0.06)'; });
            tab.addEventListener('mouseleave', function() { if (!isActive) tab.style.background = 'transparent'; });
            tab.addEventListener('click', function() {
                _exportView = vm.value;
                // Re-render just the code area
                updateCodePreview(codeArea);
            });
            viewRow.appendChild(tab);
        });
        codeGroup.appendChild(viewRow);

        // Buttons row
        var btnsRow = el('div', { style: { display: 'flex', gap: '4px', marginBottom: '8px' } });

        var copyBtn = makeBtn('Copy', 'sb-btn-primary sb-btn-sm', function() {
            copyToClipboard(getExportText());
        }, 'copy');
        copyBtn.style.flex = '1';
        copyBtn.style.justifyContent = 'center';
        btnsRow.appendChild(copyBtn);

        // Save to file button (local dev only)
        var introFileName = detectIntroFileName();
        if (introFileName) {
            var saveBtn = makeBtn('Save to file', 'sb-btn-green sb-btn-sm', function() {
                var code = codegen.generateFullCode();
                var csrfToken = document.querySelector('meta[name="csrf-token"]');
                var headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
                if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken.getAttribute('content');
                fetch('/tutorial-save', {
                    method: 'POST', headers: headers,
                    body: JSON.stringify({ path: introFileName, code: code }),
                }).then(function(r) { return r.json(); }).then(function(data) {
                    if (data.ok) {
                        flashStatus('Saved: ' + data.file);
                    } else {
                        flashStatus('Error: ' + (data.error || 'unknown'));
                    }
                }).catch(function(err) {
                    flashStatus('Save failed: ' + err.message);
                });
            }, 'content');
            saveBtn.style.flex = '1';
            saveBtn.style.justifyContent = 'center';
            btnsRow.appendChild(saveBtn);
        }

        codeGroup.appendChild(btnsRow);

        // File info
        if (introFileName) {
            codeGroup.appendChild(el('div', {
                textContent: 'File: resources/views/scripts/' + introFileName + '.js',
                style: { fontSize: '9px', color: '#7a7f8e', marginBottom: '6px', fontFamily: '"Fira Code", monospace' },
            }));
        }

        // Code preview area
        var codeArea = el('div');
        updateCodePreview(codeArea);
        codeGroup.appendChild(codeArea);

        container.appendChild(codeGroup);
    },
});
