// ui/path-preview.js — SVG path preview with draggable endpoint dots + bezier control point handles

import * as state from '../state';
import * as events from '../events';

var _svg = null;
var _pathEls = [];
var _cpLines = [];
var _endpointDots = [];
var _cpHandles = [];
var _refreshTimer = null;
var _dragging = null;
var _snapOutline = null;
var _snapLabel = null;

// Normalized default control points for a bezier curve
var DEFAULT_CP = { cp1: { x: 0.3, y: 0.1 }, cp2: { x: 0.7, y: 0.9 } };

export function init() {
    _svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    Object.assign(_svg.style, {
        position: 'fixed', top: '0', left: '0',
        width: '100%', height: '100%',
        pointerEvents: 'none', zIndex: '99996',
    });
    document.body.appendChild(_svg);

    _snapOutline = document.createElement('div');
    Object.assign(_snapOutline.style, {
        position: 'fixed', border: '2px solid #2ecc71', borderRadius: '4px',
        pointerEvents: 'none', zIndex: '99998', display: 'none',
        boxShadow: '0 0 0 3px rgba(46,204,113,0.2)',
    });
    document.body.appendChild(_snapOutline);

    _snapLabel = document.createElement('div');
    Object.assign(_snapLabel.style, {
        position: 'fixed', background: '#1a1a2e', color: '#2ecc71', padding: '2px 6px',
        borderRadius: '4px', fontSize: '10px', fontFamily: '"Fira Code", monospace',
        pointerEvents: 'none', zIndex: '99999', display: 'none', whiteSpace: 'nowrap',
    });
    document.body.appendChild(_snapLabel);

    document.addEventListener('mousemove', onDragMove);
    document.addEventListener('mouseup', onDragEnd);

    events.on('step-selected', scheduleRefresh);
    events.on('step-updated', scheduleRefresh);
    events.on('state-restored', scheduleRefresh);
    scheduleRefresh();
}

// --- Helpers ---

function resolveSelector(sel, step) {
    if (!sel) return null;
    if (sel.indexOf('highlight:') === 0) {
        var parts = sel.replace('highlight:', '').split(':');
        var gi = parseInt(parts[0], 10), ei = parts[1] !== undefined ? parseInt(parts[1], 10) : 0;
        if (step.highlight && step.highlight.groups && step.highlight.groups[gi]) {
            var elems = step.highlight.groups[gi].elements || step.highlight.groups[gi];
            if (Array.isArray(elems) && elems[ei]) return document.querySelector(elems[ei]);
        }
        return null;
    }
    if (sel.indexOf('hover:') === 0) {
        var idx = parseInt(sel.replace('hover:', ''), 10);
        var hovers = step.hover ? (Array.isArray(step.hover) ? step.hover : [step.hover]) : [];
        if (hovers[idx]) return document.querySelector(hovers[idx]);
        return null;
    }
    return document.querySelector(sel);
}

function getAnchorPos(el, anchor) {
    var rect = el.getBoundingClientRect();
    if (anchor && anchor.x !== undefined) return { x: rect.left + anchor.x, y: rect.top + anchor.y };
    return { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 };
}

function peekElementAt(x, y) {
    var hidden = [];
    ['#tutorial-overlay', '[data-sb]'].forEach(function(sel) {
        document.querySelectorAll(sel).forEach(function(node) {
            if (node.style.display !== 'none') {
                hidden.push({ el: node, prev: node.style.display });
                node.style.display = 'none';
            }
        });
    });
    _svg.style.display = 'none';
    _snapOutline.style.display = 'none';
    _snapLabel.style.display = 'none';
    var found = document.elementFromPoint(x, y);
    hidden.forEach(function(h) { h.el.style.display = h.prev; });
    _svg.style.display = '';
    if (found === document.body || found === document.documentElement) return null;
    return found;
}

function parseSvgPath(svgPath) {
    if (!svgPath) return JSON.parse(JSON.stringify(DEFAULT_CP));
    var m = svgPath.match(/C\s*([\d.e+-]+)[,\s]+([\d.e+-]+)\s+([\d.e+-]+)[,\s]+([\d.e+-]+)/i);
    if (m) return { cp1: { x: parseFloat(m[1]), y: parseFloat(m[2]) }, cp2: { x: parseFloat(m[3]), y: parseFloat(m[4]) } };
    return JSON.parse(JSON.stringify(DEFAULT_CP));
}

function cpToSvgPath(cp) {
    return 'M 0,0 C ' + cp.cp1.x.toFixed(2) + ',' + cp.cp1.y.toFixed(2) + ' ' + cp.cp2.x.toFixed(2) + ',' + cp.cp2.y.toFixed(2) + ' 1,1';
}

// --- SVG element creators ---

function svgEl(tag, attrs) {
    var e = document.createElementNS('http://www.w3.org/2000/svg', tag);
    if (attrs) Object.keys(attrs).forEach(function(k) { e.setAttribute(k, attrs[k]); });
    return e;
}

// --- Cleanup ---

function clearAll() {
    _pathEls.forEach(function(p) { if (p.parentNode) p.parentNode.removeChild(p); });
    _cpLines.forEach(function(l) { if (l.parentNode) l.parentNode.removeChild(l); });
    _endpointDots.forEach(function(d) { if (d.el.parentNode) d.el.parentNode.removeChild(d.el); });
    _cpHandles.forEach(function(h) { if (h.el.parentNode) h.el.parentNode.removeChild(h.el); });
    _pathEls = []; _cpLines = []; _endpointDots = []; _cpHandles = [];
}

// --- Endpoint dot (draggable, snaps to elements) ---

function createEndpointDot(color, label, type, wpIndex) {
    var div = document.createElement('div');
    Object.assign(div.style, {
        position: 'fixed', width: '16px', height: '16px', borderRadius: '50%',
        backgroundColor: color, border: '2px solid #fff', cursor: 'grab',
        zIndex: '99999', transform: 'translate(-50%, -50%)',
        boxShadow: '0 0 6px ' + color + '66',
    });
    // Label
    var lbl = document.createElement('div');
    Object.assign(lbl.style, {
        position: 'absolute', top: '-18px', left: '50%', transform: 'translateX(-50%)',
        fontSize: '9px', fontWeight: '700', color: color, whiteSpace: 'nowrap',
        fontFamily: 'system-ui, sans-serif', textShadow: '0 1px 3px rgba(0,0,0,0.8)',
    });
    lbl.textContent = label;
    div.appendChild(lbl);
    document.body.appendChild(div);

    div.addEventListener('mousedown', function(e) {
        e.preventDefault(); e.stopPropagation();
        _dragging = { el: div, dragType: 'endpoint', type: type, wpIndex: wpIndex };
        div.style.cursor = 'grabbing';
    });

    return { el: div, type: type, wpIndex: wpIndex };
}

// --- Control point handle (draggable, adjusts curve) ---

function createCpHandle(color, segIndex, cpKey) {
    var div = document.createElement('div');
    Object.assign(div.style, {
        position: 'fixed', width: '10px', height: '10px', borderRadius: '50%',
        backgroundColor: color, border: '2px solid #fff', cursor: 'grab',
        zIndex: '99999', transform: 'translate(-50%, -50%)',
    });
    document.body.appendChild(div);

    div.addEventListener('mousedown', function(e) {
        e.preventDefault(); e.stopPropagation();
        _dragging = { el: div, dragType: 'cp', segIndex: segIndex, cpKey: cpKey };
        div.style.cursor = 'grabbing';
    });

    return { el: div, segIndex: segIndex, cpKey: cpKey };
}

// --- Drag handling ---

function onDragMove(e) {
    if (!_dragging) return;
    var x = e.clientX, y = e.clientY;
    _dragging.el.style.left = x + 'px';
    _dragging.el.style.top = y + 'px';

    if (_dragging.dragType === 'endpoint') {
        var target = peekElementAt(x, y);
        if (target) {
            var rect = target.getBoundingClientRect();
            Object.assign(_snapOutline.style, {
                display: 'block', left: rect.left + 'px', top: rect.top + 'px',
                width: rect.width + 'px', height: rect.height + 'px',
            });
            var sel = typeof TutorialEngine !== 'undefined' && TutorialEngine.bestSelector ? TutorialEngine.bestSelector(target) : '';
            _snapLabel.textContent = sel + '  (' + Math.round(x - rect.left) + ', ' + Math.round(y - rect.top) + ')';
            _snapLabel.style.display = 'block';
            _snapLabel.style.left = rect.left + 'px';
            _snapLabel.style.top = (rect.top - 20) + 'px';
        } else {
            _snapOutline.style.display = 'none';
            _snapLabel.style.display = 'none';
        }
    }

    // Live update visuals
    updateVisualsFromDom();
}

function onDragEnd(e) {
    if (!_dragging) return;
    var x = e.clientX, y = e.clientY;
    _dragging.el.style.cursor = 'grab';
    var dragInfo = _dragging;
    _dragging = null;
    _snapOutline.style.display = 'none';
    _snapLabel.style.display = 'none';

    var idx = state.getSelectedIndex();
    var step = state.getCurrentStep();
    if (!step || !step.cursor) { scheduleRefresh(); return; }

    if (dragInfo.dragType === 'endpoint') {
        var target = peekElementAt(x, y);
        if (!target) { scheduleRefresh(); return; }
        var sel = typeof TutorialEngine !== 'undefined' && TutorialEngine.bestSelector ? TutorialEngine.bestSelector(target) : null;
        if (!sel) { scheduleRefresh(); return; }
        var rect = target.getBoundingClientRect();
        var anchor = { x: Math.round(x - rect.left), y: Math.round(y - rect.top) };
        var cursor = JSON.parse(JSON.stringify(step.cursor));
        if (dragInfo.type === 'from') { cursor.from = sel; cursor.fromAnchor = anchor; }
        else if (dragInfo.type === 'to') { cursor.to = sel; cursor.toAnchor = anchor; }
        else if (dragInfo.type === 'waypoint' && cursor.waypoints && cursor.waypoints[dragInfo.wpIndex]) {
            cursor.waypoints[dragInfo.wpIndex].target = sel;
        }
        state.updateStep(idx, 'cursor', cursor);
    } else if (dragInfo.dragType === 'cp') {
        // Update svgPath from control point positions
        saveCpPositions(dragInfo.segIndex);
    }
}

function saveCpPositions(segIndex) {
    var step = state.getCurrentStep();
    if (!step || !step.cursor) return;
    var idx = state.getSelectedIndex();
    var segments = getSegments(step);
    if (!segments[segIndex]) return;
    var points = getPoints(step);
    var f = points[segIndex], t = points[segIndex + 1];
    if (!f || !t) return;
    var dx = t.x - f.x, dy = t.y - f.y;
    if (dx === 0 && dy === 0) return;

    var handle1 = _cpHandles[segIndex * 2];
    var handle2 = _cpHandles[segIndex * 2 + 1];
    if (!handle1 || !handle2) return;

    var h1x = parseFloat(handle1.el.style.left), h1y = parseFloat(handle1.el.style.top);
    var h2x = parseFloat(handle2.el.style.left), h2y = parseFloat(handle2.el.style.top);
    var cp1 = { x: (h1x - f.x) / dx, y: (h1y - f.y) / dy };
    var cp2 = { x: (h2x - f.x) / dx, y: (h2y - f.y) / dy };
    var svgPath = 'M 0,0 C ' + cp1.x.toFixed(2) + ',' + cp1.y.toFixed(2) + ' ' + cp2.x.toFixed(2) + ',' + cp2.y.toFixed(2) + ' 1,1';

    var cursor = JSON.parse(JSON.stringify(step.cursor));
    if (cursor.waypoints && cursor.waypoints[segIndex]) {
        cursor.waypoints[segIndex].svgPath = svgPath;
    } else {
        cursor.svgPath = svgPath;
    }
    state.updateStep(state.getSelectedIndex(), 'cursor', cursor);
}

// --- Get resolved points and segments ---

function getPoints(step) {
    var points = [];
    var c = step.cursor;
    if (!c || !c.from) return points;
    var fromEl = resolveSelector(c.from, step);
    if (!fromEl) return points;
    points.push(getAnchorPos(fromEl, c.fromAnchor));
    if (c.waypoints && c.waypoints.length) {
        c.waypoints.forEach(function(wp) {
            var wpEl = resolveSelector(wp.target, step);
            if (wpEl) points.push(getAnchorPos(wpEl, null));
        });
    }
    if (c.to) {
        var toEl = resolveSelector(c.to, step);
        if (toEl) points.push(getAnchorPos(toEl, c.toAnchor));
    }
    return points;
}

function getSegments(step) {
    var c = step.cursor;
    var segs = [];
    if (c.waypoints && c.waypoints.length) {
        c.waypoints.forEach(function(wp) { segs.push(parseSvgPath(wp.svgPath)); });
    } else if (c.to) {
        segs.push(parseSvgPath(c.svgPath));
    }
    return segs;
}

// --- Update SVG paths and CP lines from DOM positions ---

function updateVisualsFromDom() {
    var step = state.getCurrentStep();
    if (!step || !step.cursor) return;
    var points = [];
    _endpointDots.forEach(function(d) {
        points.push({ x: parseFloat(d.el.style.left), y: parseFloat(d.el.style.top) });
    });
    if (points.length < 2) return;

    for (var i = 0; i < _pathEls.length; i++) {
        var f = points[i], t = points[i + 1];
        if (!f || !t) continue;
        var h1 = _cpHandles[i * 2], h2 = _cpHandles[i * 2 + 1];
        if (h1 && h2) {
            var c1x = parseFloat(h1.el.style.left), c1y = parseFloat(h1.el.style.top);
            var c2x = parseFloat(h2.el.style.left), c2y = parseFloat(h2.el.style.top);
            _pathEls[i].setAttribute('d', 'M' + f.x + ',' + f.y + ' C' + c1x + ',' + c1y + ' ' + c2x + ',' + c2y + ' ' + t.x + ',' + t.y);
            // CP lines
            var li = i * 2;
            if (_cpLines[li]) { _cpLines[li].setAttribute('x1', f.x); _cpLines[li].setAttribute('y1', f.y); _cpLines[li].setAttribute('x2', c1x); _cpLines[li].setAttribute('y2', c1y); }
            if (_cpLines[li + 1]) { _cpLines[li + 1].setAttribute('x1', t.x); _cpLines[li + 1].setAttribute('y1', t.y); _cpLines[li + 1].setAttribute('x2', c2x); _cpLines[li + 1].setAttribute('y2', c2y); }
        }
    }
}

// --- Main refresh ---

function scheduleRefresh() {
    if (_dragging) return;
    if (_refreshTimer) clearTimeout(_refreshTimer);
    _refreshTimer = setTimeout(refresh, 100);
}

function refresh() {
    clearAll();
    _svg.innerHTML = '';

    var step = state.getCurrentStep();
    if (!step || !step.cursor || !step.cursor.from) return;

    var points = getPoints(step);
    if (points.length < 1) return;

    var segments = getSegments(step);

    // Endpoint dots
    var colors = ['#4caf50']; // from = green
    var labels = ['FROM'];
    if (step.cursor.waypoints) {
        step.cursor.waypoints.forEach(function(wp, i) { colors.push('#4fc3f7'); labels.push('WP' + (i + 1)); });
    }
    if (step.cursor.to) { colors.push('#f44336'); labels.push('TO'); }

    points.forEach(function(p, i) {
        var type = i === 0 ? 'from' : (i === points.length - 1 && step.cursor.to) ? 'to' : 'waypoint';
        var wpIdx = type === 'waypoint' ? i - 1 : undefined;
        var dot = createEndpointDot(colors[i], labels[i], type, wpIdx);
        dot.el.style.left = p.x + 'px';
        dot.el.style.top = p.y + 'px';
        _endpointDots.push(dot);
    });

    // Segments: SVG paths + CP lines + CP handles
    for (var i = 0; i < segments.length && i < points.length - 1; i++) {
        var f = points[i], t = points[i + 1], seg = segments[i];
        var dx = t.x - f.x, dy = t.y - f.y;
        var c1x = f.x + seg.cp1.x * dx, c1y = f.y + seg.cp1.y * dy;
        var c2x = f.x + seg.cp2.x * dx, c2y = f.y + seg.cp2.y * dy;

        // SVG bezier path
        var pathEl = svgEl('path', {
            d: 'M' + f.x + ',' + f.y + ' C' + c1x + ',' + c1y + ' ' + c2x + ',' + c2y + ' ' + t.x + ',' + t.y,
            fill: 'none', stroke: '#ffd700', 'stroke-width': '2.5', 'stroke-dasharray': '8,4',
        });
        _svg.appendChild(pathEl);
        _pathEls.push(pathEl);

        // CP lines (dashed, connecting endpoint to its control point)
        var l1 = svgEl('line', { x1: f.x, y1: f.y, x2: c1x, y2: c1y, stroke: '#4fc3f7', 'stroke-width': '1', 'stroke-dasharray': '4,3' });
        var l2 = svgEl('line', { x1: t.x, y1: t.y, x2: c2x, y2: c2y, stroke: '#4fc3f7', 'stroke-width': '1', 'stroke-dasharray': '4,3' });
        _svg.appendChild(l1); _svg.appendChild(l2);
        _cpLines.push(l1, l2);

        // CP drag handles
        var h1 = createCpHandle('#ff9800', i, 'cp1');
        h1.el.style.left = c1x + 'px'; h1.el.style.top = c1y + 'px';
        _cpHandles.push(h1);

        var h2 = createCpHandle('#e91e63', i, 'cp2');
        h2.el.style.left = c2x + 'px'; h2.el.style.top = c2y + 'px';
        _cpHandles.push(h2);
    }
}

export function destroy() {
    clearAll();
    if (_svg && _svg.parentNode) _svg.parentNode.removeChild(_svg);
    if (_snapOutline && _snapOutline.parentNode) _snapOutline.parentNode.removeChild(_snapOutline);
    if (_snapLabel && _snapLabel.parentNode) _snapLabel.parentNode.removeChild(_snapLabel);
    document.removeEventListener('mousemove', onDragMove);
    document.removeEventListener('mouseup', onDragEnd);
    _svg = null; _dragging = null;
    events.off('step-selected', scheduleRefresh);
    events.off('step-updated', scheduleRefresh);
    events.off('state-restored', scheduleRefresh);
}
