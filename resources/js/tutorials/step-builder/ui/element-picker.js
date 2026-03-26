// ui/element-picker.js — Enhanced element picker with dimensions, breadcrumb, keyboard nav

import { el } from '../helpers';
import * as events from '../events';

var _active = false;
var _callback = null;
var _cleanup = null;
var _ancestorIndex = 0;
var _ancestors = [];
var _currentTarget = null;

export function init() {
    events.on('pick-element', function(data) {
        startPick(data.callback, data.multi);
    });
    events.on('escape-pressed', function() {
        if (_active) cancelPick();
    });
}

function startPick(callback, multi) {
    if (_active) cancelPick();
    _active = true;
    _callback = callback;
    _ancestorIndex = 0;
    _ancestors = [];
    _currentTarget = null;

    var overlay = document.getElementById('tutorial-overlay');
    var tutContainer = document.getElementById('tutorial-container');
    var sbPanel = document.querySelector('[data-sb]');

    // Save original styles
    var savedOverlayPE = overlay ? overlay.style.pointerEvents : '';
    var savedContainerPE = tutContainer ? tutContainer.style.pointerEvents : '';

    // Let clicks pass through overlay backdrop, but keep container (avatar/bubble) selectable
    if (overlay) overlay.style.pointerEvents = 'none';
    if (tutContainer) tutContainer.style.pointerEvents = 'auto';
    if (sbPanel) sbPanel.style.pointerEvents = 'none';
    document.body.style.cursor = 'crosshair';

    // Create hover outline + label
    var hoverOutline = el('div', { style: {
        position: 'fixed', border: '2px solid #6c8aff', borderRadius: '4px', pointerEvents: 'none',
        zIndex: '100001', display: 'none', transition: 'all 0.08s ease',
        boxShadow: '0 0 0 3px rgba(108,138,255,0.2)',
    }});
    var hoverLabel = el('div', { style: {
        position: 'fixed', background: '#1a1a2e', color: '#6c8aff', padding: '3px 8px',
        borderRadius: '4px', fontSize: '10px', fontFamily: '"Fira Code", monospace', pointerEvents: 'none',
        zIndex: '100002', display: 'none', whiteSpace: 'nowrap',
    }});
    var hoverDim = el('span', { style: {
        position: 'fixed', background: '#1a1a2e', color: '#f1c40f', padding: '2px 6px',
        borderRadius: '4px', fontSize: '9px', fontFamily: '"Fira Code", monospace', pointerEvents: 'none',
        zIndex: '100002', display: 'none', whiteSpace: 'nowrap',
    }});
    var hoverBreadcrumb = el('div', { style: {
        position: 'fixed', background: '#1a1a2e', color: '#7a7f8e', padding: '2px 6px',
        borderRadius: '4px', fontSize: '9px', fontFamily: '"Fira Code", monospace', pointerEvents: 'none',
        zIndex: '100002', display: 'none', whiteSpace: 'nowrap',
    }});
    document.body.appendChild(hoverOutline);
    document.body.appendChild(hoverLabel);
    document.body.appendChild(hoverDim);
    document.body.appendChild(hoverBreadcrumb);

    function getElementUnder(x, y) {
        hoverOutline.style.display = 'none';
        hoverLabel.style.display = 'none';
        hoverDim.style.display = 'none';
        hoverBreadcrumb.style.display = 'none';

        var found = document.elementFromPoint(x, y);

        // If we hit the overlay backdrop itself (not its children), peek through
        if (found && found === overlay) {
            overlay.style.display = 'none';
            found = document.elementFromPoint(x, y);
            overlay.style.display = '';
        }

        hoverOutline.style.display = '';
        hoverLabel.style.display = '';
        hoverDim.style.display = '';
        hoverBreadcrumb.style.display = '';

        return found;
    }

    function buildAncestorList(target) {
        _ancestors = [];
        var node = target;
        while (node && node !== document.body && node !== document.documentElement) {
            _ancestors.push(node);
            node = node.parentElement;
        }
    }

    function getEffectiveTarget() {
        return _ancestors[Math.min(_ancestorIndex, _ancestors.length - 1)] || _currentTarget;
    }

    function updateDisplay(target) {
        if (!target || target === hoverOutline || target === hoverLabel) {
            hoverOutline.style.display = 'none';
            hoverLabel.style.display = 'none';
            hoverDim.style.display = 'none';
            hoverBreadcrumb.style.display = 'none';
            return;
        }
        // Skip sidebar elements
        var node = target;
        while (node && node !== document.body) {
            if (node.hasAttribute && node.hasAttribute('data-sb')) {
                hoverOutline.style.display = 'none';
                hoverLabel.style.display = 'none';
                hoverDim.style.display = 'none';
                hoverBreadcrumb.style.display = 'none';
                return;
            }
            node = node.parentElement;
        }

        var rect = target.getBoundingClientRect();
        Object.assign(hoverOutline.style, {
            display: 'block',
            left: rect.left + 'px', top: rect.top + 'px',
            width: rect.width + 'px', height: rect.height + 'px',
        });

        var sel = typeof TutorialEngine !== 'undefined' && TutorialEngine.bestSelector
            ? TutorialEngine.bestSelector(target)
            : buildFallbackSelector(target);
        hoverLabel.textContent = sel;
        hoverLabel.style.display = 'block';
        hoverLabel.style.left = rect.left + 'px';
        hoverLabel.style.top = (rect.top - 22 > 5 ? rect.top - 22 : rect.bottom + 4) + 'px';

        // Dimensions
        hoverDim.textContent = Math.round(rect.width) + ' × ' + Math.round(rect.height);
        hoverDim.style.display = 'block';
        hoverDim.style.left = (rect.left + hoverLabel.offsetWidth + 6) + 'px';
        hoverDim.style.top = hoverLabel.style.top;

        // Breadcrumb
        var crumbs = [];
        var n = target;
        for (var i = 0; i < 3 && n && n !== document.body; i++) {
            var tag = n.tagName.toLowerCase();
            if (n.id) tag += '#' + n.id;
            else if (n.className && typeof n.className === 'string') {
                var cls = n.className.split(/\s+/).filter(function(c) { return c && c.indexOf('tutorial') === -1 && c.indexOf('sb-') === -1; })[0];
                if (cls) tag += '.' + cls;
            }
            crumbs.unshift(tag);
            n = n.parentElement;
        }
        hoverBreadcrumb.textContent = crumbs.join(' > ');
        hoverBreadcrumb.style.display = 'block';
        hoverBreadcrumb.style.left = rect.left + 'px';
        var breadcrumbTop = parseInt(hoverLabel.style.top) - 16;
        if (breadcrumbTop < 5) breadcrumbTop = parseInt(hoverLabel.style.top) + 18;
        hoverBreadcrumb.style.top = breadcrumbTop + 'px';
    }

    function onMove(e) {
        var target = getElementUnder(e.clientX, e.clientY);
        if (!target) {
            hoverOutline.style.display = 'none';
            hoverLabel.style.display = 'none';
            hoverDim.style.display = 'none';
            hoverBreadcrumb.style.display = 'none';
            return;
        }
        if (target !== _currentTarget) {
            _currentTarget = target;
            _ancestorIndex = 0;
            buildAncestorList(target);
        }
        updateDisplay(getEffectiveTarget());
    }

    function onClick(e) {
        e.preventDefault();
        e.stopPropagation();
        var target = getEffectiveTarget();
        if (!target) return;
        // Skip sidebar
        var node = target;
        while (node && node !== document.body) {
            if (node.hasAttribute && node.hasAttribute('data-sb')) return;
            node = node.parentElement;
        }

        var sel = typeof TutorialEngine !== 'undefined' && TutorialEngine.bestSelector
            ? TutorialEngine.bestSelector(target)
            : buildFallbackSelector(target);
        var rect = target.getBoundingClientRect();
        var anchor = { x: Math.round(e.clientX - rect.left), y: Math.round(e.clientY - rect.top) };

        if (_callback) _callback(sel, anchor);
        if (!multi) doCleanup();
    }

    function onKeyDown(e) {
        if (!_active) return;
        if (e.key === 'ArrowUp' && _ancestors.length > 1) {
            e.preventDefault();
            _ancestorIndex = Math.min(_ancestorIndex + 1, _ancestors.length - 1);
            updateDisplay(getEffectiveTarget());
        }
        if (e.key === 'ArrowDown' && _ancestorIndex > 0) {
            e.preventDefault();
            _ancestorIndex--;
            updateDisplay(getEffectiveTarget());
        }
        if (e.key === 'Escape') {
            doCleanup();
        }
    }

    document.addEventListener('mousemove', onMove, true);
    document.addEventListener('click', onClick, true);
    document.addEventListener('keydown', onKeyDown, true);

    function doCleanup() {
        _active = false;
        _callback = null;
        document.removeEventListener('mousemove', onMove, true);
        document.removeEventListener('click', onClick, true);
        document.removeEventListener('keydown', onKeyDown, true);
        if (hoverOutline.parentNode) hoverOutline.parentNode.removeChild(hoverOutline);
        if (hoverLabel.parentNode) hoverLabel.parentNode.removeChild(hoverLabel);
        if (hoverDim.parentNode) hoverDim.parentNode.removeChild(hoverDim);
        if (hoverBreadcrumb.parentNode) hoverBreadcrumb.parentNode.removeChild(hoverBreadcrumb);
        document.body.style.cursor = '';
        // Restore pointer events
        if (overlay) overlay.style.pointerEvents = savedOverlayPE;
        if (tutContainer) tutContainer.style.pointerEvents = savedContainerPE;
        if (sbPanel) sbPanel.style.pointerEvents = '';
    }

    _cleanup = doCleanup;
}

function cancelPick() {
    if (_cleanup) {
        _cleanup();
        _cleanup = null;
    }
}

function buildFallbackSelector(target) {
    if (target.id) return '#' + target.id;
    var tag = target.tagName.toLowerCase();
    if (target.className && typeof target.className === 'string') {
        var cls = target.className.split(/\s+/).filter(function(c) { return c; })[0];
        if (cls) return tag + '.' + cls;
    }
    return tag;
}

export function isActive() { return _active; }
export function cancel() { cancelPick(); }
