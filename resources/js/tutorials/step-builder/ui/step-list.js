// ui/step-list.js — Step list with branch tabs and drag & drop reorder

import { el, makeBtn, copyToClipboard } from '../helpers';
import { iconEl } from '../icons';
import * as state from '../state';
import * as events from '../events';
import * as codegen from '../codegen';

var BRANCH_COLORS = ['#6c8aff', '#2ecc71', '#e74c3c', '#f1c40f', '#9b59b6', '#e67e22', '#1abc9c', '#e84393'];
var _container = null;
var _collapsed = false;
var _activeTab = null; // null = All
var _contextMenu = null;
var _menuOpen = false;

function closeContextMenu() {
    if (_contextMenu && _contextMenu.parentNode) _contextMenu.parentNode.removeChild(_contextMenu);
    _contextMenu = null;
    _menuOpen = false;
    render(); // Re-render now that menu is closed
}

function showContextMenu(x, y, stepIdx, step) {
    closeContextMenu();
    _menuOpen = true;

    _contextMenu = el('div', { style: {
        position: 'fixed', left: x + 'px', top: y + 'px', zIndex: '100010',
        background: 'linear-gradient(170deg, #2a2a3d, #1e1e2e)',
        border: '1px solid rgba(255,255,255,0.1)', borderRadius: '10px',
        padding: '4px', minWidth: '180px',
        boxShadow: '0 8px 30px rgba(0,0,0,0.5)',
        fontFamily: 'system-ui, -apple-system, sans-serif', fontSize: '12px',
    }});

    var items = [
        { icon: 'add', label: 'Insert After', color: '#2ecc71', action: function() { state.addStep(stepIdx, step._branch || undefined); } },
        { icon: 'duplicate', label: 'Duplicate', color: '#6c8aff', action: function() { state.duplicateStep(stepIdx); } },
        { type: 'separator' },
        { icon: 'copy', label: 'Copy Step Code', color: '#b0b4c0', action: function() {
            copyToClipboard(codegen.generateCopyStepText(stepIdx));
        }},
        { type: 'separator' },
        { icon: 'chevronUp', label: 'Move Up', color: '#b0b4c0', disabled: stepIdx === 0, action: function() { state.moveStep(stepIdx, stepIdx - 1); } },
        { icon: 'chevronDown', label: 'Move Down', color: '#b0b4c0', disabled: stepIdx >= state.getSteps().length - 1, action: function() { state.moveStep(stepIdx, stepIdx + 1); } },
        { type: 'separator' },
        { icon: 'trash', label: 'Delete', color: '#e74c3c', disabled: state.getSteps().length <= 1, action: function() { state.removeStep(stepIdx); } },
    ];

    items.forEach(function(item) {
        if (item.type === 'separator') {
            _contextMenu.appendChild(el('div', { style: { height: '1px', background: 'rgba(255,255,255,0.06)', margin: '3px 8px' } }));
            return;
        }
        var row = el('div', { style: {
            display: 'flex', alignItems: 'center', gap: '8px', padding: '6px 10px',
            borderRadius: '6px', cursor: item.disabled ? 'default' : 'pointer',
            color: item.disabled ? 'rgba(255,255,255,0.2)' : item.color,
            opacity: item.disabled ? '0.4' : '1',
            transition: 'background 0.1s',
        }});
        row.appendChild(iconEl(item.icon, 14));
        row.appendChild(el('span', { textContent: item.label }));

        if (!item.disabled) {
            row.addEventListener('mouseenter', function() { row.style.background = 'rgba(255,255,255,0.06)'; });
            row.addEventListener('mouseleave', function() { row.style.background = 'transparent'; });
            row.addEventListener('click', function() {
                closeContextMenu();
                item.action();
            });
        }
        _contextMenu.appendChild(row);
    });

    document.body.appendChild(_contextMenu);

    // Clamp to viewport
    var rect = _contextMenu.getBoundingClientRect();
    if (rect.right > window.innerWidth) _contextMenu.style.left = (window.innerWidth - rect.width - 8) + 'px';
    if (rect.bottom > window.innerHeight) _contextMenu.style.top = (window.innerHeight - rect.height - 8) + 'px';

    // Close on click outside
    setTimeout(function() {
        document.addEventListener('click', function _close() {
            document.removeEventListener('click', _close);
            closeContextMenu();
        });
        document.addEventListener('contextmenu', function _close2() {
            document.removeEventListener('contextmenu', _close2);
            closeContextMenu();
        });
    }, 0);
}

export function init(container) {
    _container = container;
    render();
    events.on('step-selected', render);
    events.on('state-restored', render);
    events.on('step-added', render);
    events.on('step-removed', render);
    events.on('step-moved', render);
    events.on('step-updated', render);
}

function getBranches(steps) {
    var branches = [];
    var colorMap = {};
    var ci = 0;
    steps.forEach(function(s) {
        var b = s._branch || '_main';
        if (!colorMap[b]) {
            colorMap[b] = BRANCH_COLORS[ci++ % BRANCH_COLORS.length];
            branches.push(b);
        }
    });
    return { order: branches, colors: colorMap };
}

function render() {
    if (!_container || _menuOpen) return;
    _container.innerHTML = '';

    var steps = state.getSteps();
    var selectedIdx = state.getSelectedIndex();
    var info = getBranches(steps);
    var hasBranches = info.order.length > 1 || (info.order.length === 1 && info.order[0] !== '_main');

    // Auto-select tab of current step
    var currentBranch = steps[selectedIdx] ? (steps[selectedIdx]._branch || '_main') : '_main';
    if (_activeTab && info.order.indexOf(_activeTab) === -1) _activeTab = null;

    // Header
    var header = el('div', { className: 'sb-step-list-header' });
    header.appendChild(el('span', { textContent: 'STEPS (' + steps.length + ')', className: 'sb-step-list-header-title' }));

    var actions = el('div', { style: { display: 'flex', gap: '4px' } });
    actions.appendChild(makeBtn('', 'sb-btn-ghost sb-btn-icon sb-btn-sm', function() {
        if (_activeTab) {
            // Find last step index of this branch to insert after it
            var lastIdx = -1;
            steps.forEach(function(s, idx) {
                if ((s._branch || '_main') === _activeTab) lastIdx = idx;
            });
            var insertAfter = lastIdx >= 0 ? lastIdx : selectedIdx;
            state.addStep(insertAfter, _activeTab === '_main' ? undefined : _activeTab);
        } else {
            state.addStep(selectedIdx);
        }
    }, 'add'));
    actions.appendChild(makeBtn('', 'sb-btn-ghost sb-btn-icon sb-btn-sm', function() {
        _collapsed = !_collapsed;
        render();
    }, _collapsed ? 'chevronRight' : 'chevronDown'));
    header.appendChild(actions);
    _container.appendChild(header);

    if (_collapsed) return;

    // Branch tabs
    if (hasBranches) {
        var tabBar = el('div', { style: {
            display: 'flex', gap: '2px', padding: '0 0 6px', overflowX: 'auto',
            borderBottom: '1px solid rgba(255,255,255,0.04)', marginBottom: '4px',
        }});

        // "All" tab
        var allActive = !_activeTab;
        var allTab = el('div', { style: {
            padding: '3px 8px', borderRadius: '10px', cursor: 'pointer',
            fontSize: '10px', fontWeight: '600', whiteSpace: 'nowrap', transition: 'all 0.15s',
            background: allActive ? 'rgba(108,138,255,0.2)' : 'transparent',
            color: allActive ? '#6c8aff' : '#7a7f8e',
            border: allActive ? '1px solid rgba(108,138,255,0.3)' : '1px solid transparent',
        }});
        allTab.textContent = 'All';
        allTab.addEventListener('click', function() { _activeTab = null; render(); });
        tabBar.appendChild(allTab);

        // Branch tabs
        info.order.forEach(function(b) {
            var isActive = _activeTab === b;
            var color = info.colors[b];
            var label = b === '_main' ? 'Main' : b;
            var count = steps.filter(function(s) { return (s._branch || '_main') === b; }).length;

            var tab = el('div', { style: {
                padding: '3px 8px', borderRadius: '10px', cursor: 'pointer',
                fontSize: '10px', fontWeight: '600', whiteSpace: 'nowrap', transition: 'all 0.15s',
                display: 'flex', alignItems: 'center', gap: '4px',
                background: isActive ? color + '22' : 'transparent',
                color: isActive ? color : '#7a7f8e',
                border: isActive ? '1px solid ' + color + '55' : '1px solid transparent',
            }});
            tab.appendChild(el('div', { style: { width: '6px', height: '6px', borderRadius: '50%', background: color } }));
            tab.appendChild(document.createTextNode(label + ' (' + count + ')'));
            tab.addEventListener('click', function() { _activeTab = b; render(); });
            tabBar.appendChild(tab);
        });

        _container.appendChild(tabBar);
    }

    // Filter steps by active tab
    var filteredSteps = [];
    steps.forEach(function(step, i) {
        var branch = step._branch || '_main';
        if (!_activeTab || branch === _activeTab) {
            filteredSteps.push({ step: step, index: i, branch: branch });
        }
    });

    // Step items
    var list = el('div', { style: { maxHeight: '200px', overflowY: 'auto' } });

    filteredSteps.forEach(function(item) {
        var step = item.step;
        var i = item.index;
        var branch = item.branch;
        var isActive = i === selectedIdx;
        var color = info.colors[branch];

        var row = el('div', {
            className: 'sb-step-item' + (isActive ? ' sb-active' : ''),
            draggable: 'true',
            style: { borderLeft: hasBranches ? '2px solid ' + (isActive ? color : 'transparent') : 'none' },
        });

        // Number badge
        var num = el('span', { className: 'sb-step-item-num' });
        num.textContent = i;
        if (isActive) num.style.background = color;
        row.appendChild(num);

        // Text preview
        var text = step.html || step.silentClick || '(empty)';
        if (text.length > 25) text = text.substring(0, 25) + '...';
        row.appendChild(el('span', { textContent: text, className: 'sb-step-item-text' }));

        // Feature icons
        var icons = el('span', { className: 'sb-step-item-icons' });
        if (step.cursor && step.cursor.from) icons.appendChild(iconEl('cursor', 12));
        if (step.highlight && step.highlight.groups && step.highlight.groups.length) icons.appendChild(iconEl('highlight', 12));
        if (step.silentClick) icons.appendChild(iconEl('silentClick', 12));
        if (step.autoNext !== undefined) icons.appendChild(iconEl('autoNext', 12));
        if (step.options && step.options.length) icons.appendChild(iconEl('options', 12));
        row.appendChild(icons);

        // Modified dot
        if (state.hasStepChanged(i)) {
            row.appendChild(el('span', { className: 'sb-step-item-modified' }));
        }

        // Click
        row.addEventListener('click', function() { state.selectStep(i); });

        // Right-click context menu
        row.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            e.stopPropagation();
            // Use syncSelectedIndex to avoid re-render during menu open
            state.syncSelectedIndex(i);
            showContextMenu(e.clientX, e.clientY, i, step);
        });

        // Drag & drop
        row.addEventListener('dragstart', function(e) {
            e.dataTransfer.setData('text/plain', i);
            row.classList.add('sb-dragging');
        });
        row.addEventListener('dragend', function() { row.classList.remove('sb-dragging'); });
        row.addEventListener('dragover', function(e) {
            e.preventDefault();
            row.style.borderTop = '2px solid #6c8aff';
        });
        row.addEventListener('dragleave', function() { row.style.borderTop = ''; });
        row.addEventListener('drop', function(e) {
            e.preventDefault();
            row.style.borderTop = '';
            var fromIdx = parseInt(e.dataTransfer.getData('text/plain'), 10);
            if (!isNaN(fromIdx) && fromIdx !== i) {
                state.moveStep(fromIdx, i);
            }
        });

        list.appendChild(row);
    });

    _container.appendChild(list);
}
