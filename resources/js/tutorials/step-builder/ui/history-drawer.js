// ui/history-drawer.js — Slide-in from left, undo action list, click to restore

import { el } from '../helpers';
import { iconEl } from '../icons';
import * as state from '../state';
import * as events from '../events';

var _drawer = null;
var _open = false;

export function init(drawer) {
    _drawer = drawer;
    events.on('toggle-history-drawer', toggle);
    events.on('undo-changed', render);
    events.on('escape-pressed', function() { if (_open) close(); });
    render();
}

function toggle() {
    _open = !_open;
    if (_drawer) {
        if (_open) _drawer.classList.add('sb-open');
        else _drawer.classList.remove('sb-open');
    }
    if (_open) render();
}

function close() {
    _open = false;
    if (_drawer) _drawer.classList.remove('sb-open');
}

function render() {
    if (!_drawer) return;
    _drawer.innerHTML = '';

    var undoSize = state.getUndoSize();
    var redoSize = state.getRedoSize();

    // Header
    var header = el('div', { style: {
        padding: '8px 10px', borderBottom: '1px solid rgba(255,255,255,0.06)',
        fontSize: '11px', fontWeight: '600', color: '#6c8aff', textTransform: 'uppercase',
        letterSpacing: '0.3px',
    }});
    header.appendChild(document.createTextNode('History'));
    _drawer.appendChild(header);

    // Current state
    var currentItem = el('div', { className: 'sb-history-item sb-current' });
    currentItem.appendChild(iconEl('history', 12));
    currentItem.appendChild(document.createTextNode('Current state'));
    _drawer.appendChild(currentItem);

    // Undo entries
    for (var i = undoSize - 1; i >= 0; i--) {
        var item = el('div', { className: 'sb-history-item' });
        item.appendChild(iconEl('undo', 12));
        item.appendChild(document.createTextNode('Change ' + (i + 1)));

        // Click to undo to this point
        (function(count) {
            item.addEventListener('click', function() {
                for (var j = 0; j < count; j++) {
                    state.undo();
                }
            });
        })(undoSize - i);

        _drawer.appendChild(item);
    }

    // Redo entries
    for (var i = 0; i < redoSize; i++) {
        var item = el('div', { className: 'sb-history-item', style: { opacity: '0.5' } });
        item.appendChild(iconEl('redo', 12));
        item.appendChild(document.createTextNode('Redo ' + (i + 1)));

        (function(count) {
            item.addEventListener('click', function() {
                for (var j = 0; j < count; j++) {
                    state.redo();
                }
            });
        })(i + 1);

        _drawer.appendChild(item);
    }

    // Empty state
    if (undoSize === 0 && redoSize === 0) {
        _drawer.appendChild(el('div', {
            textContent: 'No history yet',
            style: { padding: '20px 10px', color: '#7a7f8e', fontSize: '11px', textAlign: 'center' },
        }));
    }
}
