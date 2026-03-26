// ui/history-drawer.js — Slide-in from left, detailed undo action list

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

function timeAgo(ts) {
    var diff = Math.round((Date.now() - ts) / 1000);
    if (diff < 5) return 'just now';
    if (diff < 60) return diff + 's ago';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    return Math.floor(diff / 3600) + 'h ago';
}

function actionIcon(label) {
    if (label.indexOf('Add') === 0) return 'add';
    if (label.indexOf('Delete') === 0) return 'trash';
    if (label.indexOf('Duplicate') === 0) return 'duplicate';
    if (label.indexOf('Move') === 0) return 'actions';
    if (label.indexOf('Edit') === 0) return 'content';
    return 'history';
}

function actionColor(label) {
    if (label.indexOf('Add') === 0) return '#2ecc71';
    if (label.indexOf('Delete') === 0) return '#e74c3c';
    if (label.indexOf('Duplicate') === 0) return '#6c8aff';
    if (label.indexOf('Move') === 0) return '#f1c40f';
    return '#b0b4c0';
}

function render() {
    if (!_drawer) return;
    _drawer.innerHTML = '';

    var undoLabels = state.getUndoLabels();
    var redoLabels = state.getRedoLabels();

    // Header
    var header = el('div', { style: {
        padding: '8px 10px', borderBottom: '1px solid rgba(255,255,255,0.06)',
        display: 'flex', alignItems: 'center', gap: '6px',
    }});
    header.appendChild(iconEl('history', 14));
    header.appendChild(el('span', { textContent: 'History', style: {
        fontSize: '11px', fontWeight: '600', color: '#6c8aff', textTransform: 'uppercase',
        letterSpacing: '0.3px', flex: '1',
    }}));
    header.appendChild(el('span', { textContent: undoLabels.length + ' actions', style: {
        fontSize: '9px', color: '#7a7f8e',
    }}));
    _drawer.appendChild(header);

    // Current state
    var currentItem = el('div', { className: 'sb-history-item sb-current', style: { gap: '8px' } });
    var currentDot = el('div', { style: { width: '8px', height: '8px', borderRadius: '50%', background: '#6c8aff', flexShrink: '0' } });
    currentItem.appendChild(currentDot);
    currentItem.appendChild(el('span', { textContent: 'Current state', style: { flex: '1', fontWeight: '600' } }));
    _drawer.appendChild(currentItem);

    // Undo entries (most recent first)
    for (var i = undoLabels.length - 1; i >= 0; i--) {
        var entry = undoLabels[i];
        var color = actionColor(entry.label);
        var item = el('div', { className: 'sb-history-item', style: { gap: '8px', flexWrap: 'wrap' } });

        // Color dot
        item.appendChild(el('div', { style: { width: '6px', height: '6px', borderRadius: '50%', background: color, flexShrink: '0', marginTop: '3px' } }));

        // Icon
        var icon = iconEl(actionIcon(entry.label), 12);
        icon.style.color = color;
        icon.style.flexShrink = '0';
        item.appendChild(icon);

        // Label
        item.appendChild(el('span', { textContent: entry.label, style: { flex: '1', fontSize: '11px' } }));

        // Time ago
        item.appendChild(el('span', { textContent: timeAgo(entry.time), style: { fontSize: '9px', color: '#555', flexShrink: '0' } }));

        // Click to undo to this point
        (function(count) {
            item.addEventListener('click', function() {
                for (var j = 0; j < count; j++) state.undo();
                render();
            });
        })(undoLabels.length - i);

        _drawer.appendChild(item);
    }

    // Redo entries
    if (redoLabels.length) {
        _drawer.appendChild(el('div', { style: {
            padding: '4px 10px', fontSize: '9px', color: '#555', textTransform: 'uppercase',
            letterSpacing: '0.3px', borderTop: '1px solid rgba(255,255,255,0.04)', marginTop: '4px',
        }, textContent: 'Undone' }));

        for (var i = 0; i < redoLabels.length; i++) {
            var entry = redoLabels[i];
            var item = el('div', { className: 'sb-history-item', style: { opacity: '0.4', gap: '8px' } });
            item.appendChild(iconEl('redo', 12));
            item.appendChild(el('span', { textContent: entry.label, style: { flex: '1', fontSize: '11px' } }));
            item.appendChild(el('span', { textContent: timeAgo(entry.time), style: { fontSize: '9px', color: '#555' } }));

            (function(count) {
                item.addEventListener('click', function() {
                    for (var j = 0; j < count; j++) state.redo();
                    render();
                });
            })(i + 1);

            _drawer.appendChild(item);
        }
    }

    // Empty state
    if (undoLabels.length === 0 && redoLabels.length === 0) {
        _drawer.appendChild(el('div', {
            textContent: 'No history yet',
            style: { padding: '20px 10px', color: '#7a7f8e', fontSize: '11px', textAlign: 'center' },
        }));
    }
}
