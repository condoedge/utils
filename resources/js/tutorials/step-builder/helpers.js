// helpers.js — Shared DOM creation utilities for Step Builder v2

import { iconEl } from './icons';

// Create DOM element with attributes and children
export function el(tag, attrs, children) {
    var e = document.createElement(tag);
    if (attrs) {
        Object.keys(attrs).forEach(function(k) {
            if (k === 'style' && typeof attrs[k] === 'object') Object.assign(e.style, attrs[k]);
            else if (k === 'className') e.className = attrs[k];
            else if (k.indexOf('on') === 0) e.addEventListener(k.slice(2).toLowerCase(), attrs[k]);
            else if (k === 'textContent') e.textContent = attrs[k];
            else if (k === 'innerHTML') e.innerHTML = attrs[k];
            else if (k === 'value') e.value = attrs[k];
            else if (k === 'checked') e.checked = attrs[k];
            else if (k === 'disabled') e.disabled = attrs[k];
            else if (k === 'readOnly') e.readOnly = attrs[k];
            else if (k === 'type') e.type = attrs[k];
            else if (k === 'placeholder') e.placeholder = attrs[k];
            else e.setAttribute(k, attrs[k]);
        });
    }
    if (children) {
        (Array.isArray(children) ? children : [children]).forEach(function(c) {
            if (typeof c === 'string') e.appendChild(document.createTextNode(c));
            else if (c) e.appendChild(c);
        });
    }
    return e;
}

// Create a button with icon + text
export function makeBtn(text, className, onClick, iconName) {
    var btn = el('button', { className: 'sb-btn ' + className, onClick: onClick });
    if (iconName) {
        btn.appendChild(iconEl(iconName, 14));
    }
    if (text) {
        btn.appendChild(document.createTextNode(text));
    }
    return btn;
}

// Create a text input
export function makeInput(value, onChange, extra) {
    var inp = el('input', { type: 'text', value: value || '' });
    if (extra) Object.keys(extra).forEach(function(k) {
        if (k === 'style') Object.assign(inp.style, extra[k]);
        else inp[k] = extra[k];
    });
    inp.addEventListener('input', function() { onChange(inp.value); });
    return inp;
}

// Create a number input
export function makeNumberInput(value, onChange, extra) {
    var inp = el('input', { type: 'number', value: value });
    if (extra) Object.keys(extra).forEach(function(k) { inp[k] = extra[k]; });
    inp.addEventListener('input', function() { onChange(parseFloat(inp.value) || 0); });
    return inp;
}

// Create a select dropdown
export function makeSelect(options, selected, onChange) {
    var sel = el('select');
    options.forEach(function(o) {
        var opt = el('option', { value: o.value, textContent: o.label || o.value });
        if (o.value === selected) opt.selected = true;
        sel.appendChild(opt);
    });
    sel.addEventListener('change', function() { onChange(sel.value); });
    return sel;
}

// Create a checkbox with label
export function makeCheckbox(label, checked, onChange) {
    var lbl = el('label', { className: 'sb-checkbox' });
    var cb = el('input', { type: 'checkbox', checked: !!checked });
    cb.addEventListener('change', function() { onChange(cb.checked); });
    lbl.appendChild(cb);
    lbl.appendChild(document.createTextNode(label));
    return lbl;
}

// Create a row of elements
export function makeRow(children) {
    var r = el('div', { className: 'sb-row' });
    children.forEach(function(c) { if (c) r.appendChild(c); });
    return r;
}

// Create a labeled row: label + control
export function makeLabeledRow(labelText, control) {
    var r = el('div', { className: 'sb-row' });
    r.appendChild(el('label', { textContent: labelText, className: 'sb-label' }));
    if (typeof control === 'string') {
        r.appendChild(el('span', { textContent: control, className: 'sb-selector' }));
    } else {
        control.style.flex = '1';
        r.appendChild(control);
    }
    return r;
}

// Create a selector display with Pick button
export function makeSelectorRow(label, selector, pickBtnClass, onPick, onClear) {
    var display = el('span', {
        textContent: selector || '\u2014',
        className: 'sb-selector',
        style: { flex: '1' },
    });
    var children = [
        el('label', { textContent: label, className: 'sb-label' }),
        display,
        makeBtn('Pick', 'sb-btn-' + pickBtnClass + ' sb-btn-sm', onPick, 'pick'),
    ];
    if (selector && onClear) {
        children.push(makeBtn('', 'sb-btn-ghost sb-btn-icon sb-btn-sm', onClear, 'close'));
    }
    return makeRow(children);
}

// Flash a status message at bottom of screen
export function flashStatus(msg) {
    var flash = el('div', {
        textContent: msg,
        style: {
            position: 'fixed', bottom: '20px', left: '50%', transform: 'translateX(-50%)',
            background: 'linear-gradient(135deg, #2ecc71, #27ae60)', color: '#fff',
            padding: '8px 20px', borderRadius: '10px', fontSize: '13px', fontWeight: '600',
            zIndex: '100001', boxShadow: '0 4px 16px rgba(46,204,113,0.3)',
            opacity: '1', transition: 'opacity 0.4s ease',
            fontFamily: 'system-ui, sans-serif',
        },
    });
    document.body.appendChild(flash);
    setTimeout(function() { flash.style.opacity = '0'; }, 1500);
    setTimeout(function() { if (flash.parentNode) flash.parentNode.removeChild(flash); }, 2000);
}

// Copy text to clipboard
export function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(
            function() { flashStatus('Copied!'); },
            function() { flashStatus('Copy failed'); }
        );
    } else {
        var ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        flashStatus('Copied!');
    }
}
