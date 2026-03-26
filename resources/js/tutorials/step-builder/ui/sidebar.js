// ui/sidebar.js — Main sidebar layout with header, 3 zones, resize, collapse

import { el, makeBtn } from '../helpers';
import { iconEl } from '../icons';
import * as state from '../state';
import * as events from '../events';

export function createSidebar() {
    var panel = el('div', { 'data-sb': '', className: 'sb-sidebar' });

    // --- Resize handle (left edge) ---
    var resizeLeft = el('div', { className: 'sb-resize-left' });
    panel.appendChild(resizeLeft);
    setupResize(resizeLeft, panel);

    // --- Header ---
    var collapsed = false;
    var collapseBtn = el('button', { className: 'sb-btn sb-btn-ghost sb-btn-icon' });
    collapseBtn.appendChild(iconEl('collapse', 14));
    collapseBtn.addEventListener('click', function() {
        collapsed = !collapsed;
        if (collapsed) {
            panel.classList.add('sb-collapsed');
            document.body.style.marginRight = '0';
        } else {
            panel.classList.remove('sb-collapsed');
            document.body.style.marginRight = panel.offsetWidth + 'px';
        }
        events.emit('sidebar-collapsed', { collapsed: collapsed });
    });

    var historyBtn = el('button', { className: 'sb-btn sb-btn-ghost sb-btn-icon' });
    historyBtn.appendChild(iconEl('history', 14));
    historyBtn.addEventListener('click', function() {
        events.emit('toggle-history-drawer');
    });

    window._tutorialDevMode = true;
    var devCb = el('input', { type: 'checkbox', checked: true });
    devCb.addEventListener('change', function() { window._tutorialDevMode = devCb.checked; });
    var devLabel = el('label', { className: 'sb-checkbox', style: { fontSize: '10px' } });
    devLabel.appendChild(devCb);
    devLabel.appendChild(document.createTextNode('Dev'));

    var header = el('div', { className: 'sb-header' }, [
        iconEl('menu', 16),
        el('span', { textContent: 'Step Builder', className: 'sb-header-title' }),
        historyBtn,
        devLabel,
        collapseBtn,
    ]);
    panel.appendChild(header);

    // --- Step List zone ---
    var stepListZone = el('div', { className: 'sb-step-list' });
    panel.appendChild(stepListZone);

    // --- Detail zone (scrollable, cards go here) ---
    var detailZone = el('div', { className: 'sb-detail' });
    panel.appendChild(detailZone);

    // --- History drawer ---
    var historyDrawer = el('div', { className: 'sb-history-drawer' });
    panel.appendChild(historyDrawer);

    // Compress page
    document.body.style.marginRight = '420px';
    document.body.style.transition = 'margin-right 0.2s ease';

    return {
        panel: panel,
        header: header,
        stepListZone: stepListZone,
        detailZone: detailZone,
        historyDrawer: historyDrawer,
        isCollapsed: function() { return collapsed; },
        collapse: function() { if (!collapsed) collapseBtn.click(); },
        expand: function() { if (collapsed) collapseBtn.click(); },
    };
}

function setupResize(handle, panel) {
    handle.addEventListener('mousedown', function(e) {
        e.preventDefault();
        var startX = e.clientX;
        var startW = panel.offsetWidth;
        function onMove(e) {
            var dx = startX - e.clientX;
            var w = Math.max(320, Math.min(window.innerWidth * 0.6, startW + dx));
            panel.style.width = w + 'px';
            document.body.style.marginRight = w + 'px';
            events.emit('sidebar-resized', { width: w });
        }
        function onUp() {
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
        }
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
    });
    // Touch
    handle.addEventListener('touchstart', function(e) {
        e.preventDefault();
        var startX = e.touches[0].clientX;
        var startW = panel.offsetWidth;
        function onMove(e) {
            var dx = startX - e.touches[0].clientX;
            var w = Math.max(320, Math.min(window.innerWidth * 0.6, startW + dx));
            panel.style.width = w + 'px';
            document.body.style.marginRight = w + 'px';
        }
        function onUp() {
            document.removeEventListener('touchmove', onMove);
            document.removeEventListener('touchend', onUp);
        }
        document.addEventListener('touchmove', onMove);
        document.addEventListener('touchend', onUp);
    });
}
