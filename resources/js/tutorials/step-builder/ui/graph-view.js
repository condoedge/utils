// ui/graph-view.js — Flow graph in a full-screen modal with snake/boustrophedon layout

import { el, makeBtn } from '../helpers';
import { iconEl } from '../icons';
import * as state from '../state';
import * as events from '../events';

var BRANCH_COLORS = ['#6c8aff', '#2ecc71', '#e74c3c', '#f1c40f', '#9b59b6', '#e67e22', '#1abc9c', '#e84393'];
var NODE_W = 180;
var NODE_H = 80;
var GAP_X = 28;
var GAP_Y = 24;
var PAD = 40;
var COLS = 6; // steps per row before snaking

var _modal = null;

export function init() {
    events.on('open-graph-modal', openModal);
    events.on('escape-pressed', function() { if (_modal) closeModal(); });
}

function openModal() {
    if (_modal) closeModal();

    _modal = el('div', { style: {
        position: 'fixed', inset: '0', zIndex: '100000',
        background: 'rgba(10,10,20,0.92)', backdropFilter: 'blur(6px)',
        display: 'flex', flexDirection: 'column',
        fontFamily: 'system-ui, -apple-system, sans-serif',
    }});

    // Header
    var header = el('div', { style: {
        display: 'flex', alignItems: 'center', gap: '10px',
        padding: '12px 20px', borderBottom: '1px solid rgba(255,255,255,0.08)',
        flexShrink: '0',
    }});
    header.appendChild(iconEl('graph', 18));
    header.appendChild(el('span', { textContent: 'Step Graph', style: {
        fontSize: '16px', fontWeight: '700', color: '#e0e4ec', flex: '1',
    }}));
    header.appendChild(el('span', {
        textContent: state.getSteps().length + ' steps',
        style: { fontSize: '12px', color: '#7a7f8e' },
    }));
    header.appendChild(makeBtn('Close', 'sb-btn-ghost', closeModal, 'close'));
    _modal.appendChild(header);

    // Graph container
    var graphContainer = el('div', { style: {
        flex: '1', overflow: 'auto', position: 'relative',
    }});
    _modal.appendChild(graphContainer);

    document.body.appendChild(_modal);
    renderGraph(graphContainer);

    // --- Pan with Space held (grab & drag to scroll) ---
    var _spaceHeld = false;
    var _panning = false;
    var _panStartX = 0;
    var _panStartY = 0;
    var _scrollStartX = 0;
    var _scrollStartY = 0;

    function onKeyDown(e) {
        if (e.key === 'Escape') { closeModal(); return; }
        if (e.key === ' ' || e.code === 'Space') {
            e.preventDefault();
            if (!_spaceHeld) {
                _spaceHeld = true;
                graphContainer.style.cursor = 'grab';
            }
        }
    }
    function onKeyUp(e) {
        if (e.key === ' ' || e.code === 'Space') {
            _spaceHeld = false;
            if (!_panning) graphContainer.style.cursor = '';
        }
    }
    function onMouseDown(e) {
        if (!_spaceHeld) return;
        e.preventDefault();
        _panning = true;
        _panStartX = e.clientX;
        _panStartY = e.clientY;
        _scrollStartX = graphContainer.scrollLeft;
        _scrollStartY = graphContainer.scrollTop;
        graphContainer.style.cursor = 'grabbing';
    }
    function onMouseMove(e) {
        if (!_panning) return;
        graphContainer.scrollLeft = _scrollStartX - (e.clientX - _panStartX);
        graphContainer.scrollTop = _scrollStartY - (e.clientY - _panStartY);
    }
    function onMouseUp() {
        if (!_panning) return;
        _panning = false;
        graphContainer.style.cursor = _spaceHeld ? 'grab' : '';
    }

    document.addEventListener('keydown', onKeyDown);
    document.addEventListener('keyup', onKeyUp);
    graphContainer.addEventListener('mousedown', onMouseDown);
    document.addEventListener('mousemove', onMouseMove);
    document.addEventListener('mouseup', onMouseUp);

    _modal._keyHandler = onKeyDown;
    _modal._cleanup = function() {
        document.removeEventListener('keydown', onKeyDown);
        document.removeEventListener('keyup', onKeyUp);
        graphContainer.removeEventListener('mousedown', onMouseDown);
        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', onMouseUp);
    };
}

function closeModal() {
    if (!_modal) return;
    if (_modal._cleanup) _modal._cleanup();
    if (_modal.parentNode) _modal.parentNode.removeChild(_modal);
    _modal = null;
}

// Snake layout: row 0 left-to-right, row 1 right-to-left, etc.
function getSnakePos(index, cols) {
    var row = Math.floor(index / cols);
    var colInRow = index % cols;
    var col = (row % 2 === 0) ? colInRow : (cols - 1 - colInRow);
    return { row: row, col: col };
}

function renderGraph(container) {
    container.innerHTML = '';
    var steps = state.getSteps();
    if (!steps.length) return;
    var selectedIdx = state.getSelectedIndex();

    // Branch info
    var branchOrder = [];
    var branchColorMap = {};
    var ci = 0;
    steps.forEach(function(s) {
        var b = s._branch || '_main';
        if (!branchColorMap[b]) {
            branchColorMap[b] = BRANCH_COLORS[ci++ % BRANCH_COLORS.length];
            branchOrder.push(b);
        }
    });

    // Calculate positions with snake layout
    var positions = [];
    var maxCol = 0;
    var maxRow = 0;
    steps.forEach(function(step, i) {
        var sp = getSnakePos(i, COLS);
        var x = PAD + sp.col * (NODE_W + GAP_X);
        var y = PAD + sp.row * (NODE_H + GAP_Y);
        positions.push({ x: x, y: y, row: sp.row, col: sp.col });
        if (sp.col > maxCol) maxCol = sp.col;
        if (sp.row > maxRow) maxRow = sp.row;
    });

    var totalW = PAD * 2 + (maxCol + 1) * (NODE_W + GAP_X);
    var totalH = PAD * 2 + (maxRow + 1) * (NODE_H + GAP_Y) + 60;

    var canvas = el('div', { style: {
        position: 'relative', width: totalW + 'px', height: Math.max(totalH, 300) + 'px',
        minWidth: '100%',
    }});

    // SVG layer
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('width', totalW);
    svg.setAttribute('height', Math.max(totalH, 300));
    svg.style.position = 'absolute';
    svg.style.top = '0';
    svg.style.left = '0';
    svg.style.pointerEvents = 'none';

    // Arrow markers
    var defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
    var marker = document.createElementNS('http://www.w3.org/2000/svg', 'marker');
    marker.setAttribute('id', 'sb-arrow');
    marker.setAttribute('viewBox', '0 0 10 10');
    marker.setAttribute('refX', '8');
    marker.setAttribute('refY', '5');
    marker.setAttribute('markerWidth', '8');
    marker.setAttribute('markerHeight', '8');
    marker.setAttribute('orient', 'auto-start-reverse');
    var arrowPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    arrowPath.setAttribute('d', 'M 0 1 L 8 5 L 0 9 z');
    arrowPath.setAttribute('fill', 'rgba(108,138,255,0.5)');
    marker.appendChild(arrowPath);
    defs.appendChild(marker);
    svg.appendChild(defs);

    // Draw sequential connections with arrows
    for (var i = 0; i < positions.length - 1; i++) {
        var from = positions[i];
        var to = positions[i + 1];
        var fx = from.x + NODE_W / 2;
        var fy = from.y + NODE_H / 2;
        var tx = to.x + NODE_W / 2;
        var ty = to.y + NODE_H / 2;

        if (from.row === to.row) {
            // Same row: horizontal line with arrow
            var ltr = from.row % 2 === 0;
            var x1 = ltr ? from.x + NODE_W : from.x;
            var x2 = ltr ? to.x : to.x + NODE_W;
            var line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            line.setAttribute('x1', x1); line.setAttribute('y1', fy);
            line.setAttribute('x2', x2); line.setAttribute('y2', ty);
            line.setAttribute('stroke', 'rgba(108,138,255,0.35)');
            line.setAttribute('stroke-width', '2');
            line.setAttribute('marker-end', 'url(#sb-arrow)');
            svg.appendChild(line);
        } else {
            // Row change: U-turn connector with arrow at end
            var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            var fromBottom = from.y + NODE_H;
            var toTop = to.y;
            var midY = (fromBottom + toTop) / 2;
            path.setAttribute('d',
                'M' + fx + ',' + fromBottom +
                ' L' + fx + ',' + midY +
                ' L' + tx + ',' + midY +
                ' L' + tx + ',' + toTop
            );
            path.setAttribute('fill', 'none');
            path.setAttribute('stroke', 'rgba(108,138,255,0.35)');
            path.setAttribute('stroke-width', '2');
            path.setAttribute('stroke-linejoin', 'round');
            path.setAttribute('marker-end', 'url(#sb-arrow)');
            svg.appendChild(path);
        }
    }

    canvas.appendChild(svg);

    // Draw branch change indicators on connections
    var prevBranch = null;

    // Draw nodes
    steps.forEach(function(step, i) {
        var pos = positions[i];
        var branch = step._branch || '_main';
        var isActive = i === selectedIdx;
        var color = branchColorMap[branch];
        var branchChanged = prevBranch !== null && prevBranch !== branch;
        prevBranch = branch;

        // Branch separator label between nodes when branch changes
        if (branchChanged && i > 0) {
            var prevPos = positions[i - 1];
            var sepX = (prevPos.x + pos.x) / 2 + NODE_W / 2 - 30;
            var sepY = (prevPos.y + pos.y) / 2 + NODE_H / 2 - 8;
            if (prevPos.row !== pos.row) {
                sepX = pos.x + NODE_W / 2 - 30;
                sepY = pos.y - 14;
            }
            var branchLabel = el('div', { style: {
                position: 'absolute', left: sepX + 'px', top: sepY + 'px',
                background: color, color: '#fff', fontSize: '10px', fontWeight: '700',
                padding: '2px 10px', borderRadius: '10px', zIndex: '2',
                boxShadow: '0 2px 8px rgba(0,0,0,0.4)',
                whiteSpace: 'nowrap',
            }});
            branchLabel.textContent = branch === '_main' ? 'Main' : branch;
            canvas.appendChild(branchLabel);
        }

        var node = el('div', { style: {
            position: 'absolute', left: pos.x + 'px', top: pos.y + 'px',
            width: NODE_W + 'px', height: NODE_H + 'px',
            borderRadius: '12px',
            background: isActive ? 'rgba(108,138,255,0.12)' : 'rgba(30,30,46,0.95)',
            border: '2px solid ' + (isActive ? '#6c8aff' : color + '66'),
            display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
            gap: '2px', cursor: 'pointer', transition: 'all 0.15s', overflow: 'hidden',
            boxShadow: isActive ? '0 0 20px rgba(108,138,255,0.3)' : '0 2px 10px rgba(0,0,0,0.3)',
        }});

        // Branch color left bar
        node.appendChild(el('div', { style: {
            position: 'absolute', top: '6px', bottom: '6px', left: '0', width: '4px',
            borderRadius: '0 4px 4px 0', background: color,
        }}));

        // Step number + branch name row
        var topRow = el('div', { style: { display: 'flex', alignItems: 'center', gap: '6px' } });
        var numBadge = el('div', { style: {
            width: '26px', height: '26px', borderRadius: '50%', display: 'flex',
            alignItems: 'center', justifyContent: 'center',
            background: isActive ? '#6c8aff' : 'rgba(255,255,255,0.08)',
            color: isActive ? '#fff' : '#e0e4ec',
            fontSize: '13px', fontWeight: '800',
        }});
        numBadge.textContent = i;
        topRow.appendChild(numBadge);

        // Branch tag
        var branchTag = el('span', { style: {
            fontSize: '9px', fontWeight: '600', color: color,
            background: color + '22', padding: '1px 6px', borderRadius: '8px',
        }});
        branchTag.textContent = branch === '_main' ? 'main' : branch;
        topRow.appendChild(branchTag);
        node.appendChild(topRow);

        // Preview text
        var text = step.html || step.silentClick || '';
        if (text.length > 22) text = text.substring(0, 22) + '...';
        if (text) {
            node.appendChild(el('span', {
                textContent: text,
                style: { fontSize: '11px', color: '#b0b4c0', maxWidth: (NODE_W - 20) + 'px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' },
            }));
        }

        // Bottom bar: feature icons left + goToStep right
        var bottomBar = el('div', { style: {
            position: 'absolute', bottom: '4px', left: '8px', right: '8px',
            display: 'flex', alignItems: 'center', justifyContent: 'space-between',
        }});

        var icons = el('div', { style: { display: 'flex', gap: '3px' } });
        if (step.cursor && step.cursor.from) icons.appendChild(iconEl('cursor', 12));
        if (step.highlight && step.highlight.groups && step.highlight.groups.length) icons.appendChild(iconEl('highlight', 12));
        if (step.silentClick) icons.appendChild(iconEl('silentClick', 12));
        if (step.options && step.options.length) icons.appendChild(iconEl('options', 12));
        if (step.autoNext !== undefined) icons.appendChild(iconEl('autoNext', 12));
        if (step.showIf || step.hideIf) icons.appendChild(iconEl('conditions', 12));
        bottomBar.appendChild(icons);

        // GoToStep badge
        if (step.options) {
            var goTos = [];
            step.options.forEach(function(opt) {
                if (opt.goToStep !== undefined) goTos.push(opt.goToStep);
            });
            if (goTos.length) {
                var badge = el('div', { style: {
                    fontSize: '10px', fontWeight: '700', color: '#f1c40f',
                    background: 'rgba(241,196,15,0.15)', padding: '1px 6px', borderRadius: '6px',
                }});
                badge.textContent = '→ ' + goTos.join(', ');
                bottomBar.appendChild(badge);
            }
        }
        node.appendChild(bottomBar);

        // Modified dot
        if (state.hasStepChanged(i)) {
            node.appendChild(el('div', { style: {
                position: 'absolute', top: '6px', right: '6px',
                width: '8px', height: '8px', borderRadius: '50%', background: '#f1c40f',
                boxShadow: '0 0 4px rgba(241,196,15,0.5)',
            }}));
        }

        node.addEventListener('mouseenter', function() {
            if (!isActive) { node.style.borderColor = '#6c8aff'; node.style.transform = 'translateY(-3px)'; node.style.boxShadow = '0 6px 20px rgba(0,0,0,0.4)'; }
        });
        node.addEventListener('mouseleave', function() {
            if (!isActive) { node.style.borderColor = color + '66'; node.style.transform = 'none'; node.style.boxShadow = '0 2px 10px rgba(0,0,0,0.3)'; }
        });
        node.addEventListener('click', function() {
            state.selectStep(i);
            renderGraph(container);
        });

        canvas.appendChild(node);
    });

    container.appendChild(canvas);

    // Branch legend (sticky bottom)
    if (branchOrder.length > 1 || (branchOrder.length === 1 && branchOrder[0] !== '_main')) {
        var legend = el('div', { style: {
            position: 'sticky', bottom: '0', left: '0', right: '0',
            display: 'flex', gap: '14px', padding: '10px 20px', flexWrap: 'wrap',
            background: 'rgba(10,10,20,0.8)', backdropFilter: 'blur(4px)',
            borderTop: '1px solid rgba(255,255,255,0.06)',
        }});
        branchOrder.forEach(function(b) {
            var count = steps.filter(function(s) { return (s._branch || '_main') === b; }).length;
            var item = el('div', { style: { display: 'flex', alignItems: 'center', gap: '6px', fontSize: '12px', color: '#b0b4c0' } });
            item.appendChild(el('div', { style: { width: '10px', height: '10px', borderRadius: '3px', background: branchColorMap[b] } }));
            item.appendChild(document.createTextNode((b === '_main' ? 'Main' : b) + ' (' + count + ')'));
            legend.appendChild(item);
        });
        container.appendChild(legend);
    }

    // Scroll to active
    setTimeout(function() {
        var p = positions[selectedIdx];
        if (p) {
            container.scrollLeft = Math.max(0, p.x - container.clientWidth / 2 + NODE_W / 2);
            container.scrollTop = Math.max(0, p.y - container.clientHeight / 2 + NODE_H / 2);
        }
    }, 50);
}

function drawLine(svg, x1, y1, x2, y2, stroke, width) {
    var line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
    line.setAttribute('x1', x1); line.setAttribute('y1', y1);
    line.setAttribute('x2', x2); line.setAttribute('y2', y2);
    line.setAttribute('stroke', stroke);
    line.setAttribute('stroke-width', width);
    svg.appendChild(line);
}
