// ui/timeline.js — Horizontal timeline strip with thumbnails, screenshots, drag & drop, tab bar

import { el, makeBtn } from '../helpers';
import { iconEl } from '../icons';
import * as state from '../state';
import * as events from '../events';

var _container = null;
var _strip = null;

export function init(container) {
    _container = container;
    render();
    events.on('step-selected', render);
    events.on('state-restored', render);
    events.on('step-added', render);
    events.on('step-removed', render);
    events.on('step-moved', render);
    events.on('step-updated', render);
    events.on('screenshot-updated', render);
    events.on('view-changed', render);
}

function render() {
    if (!_container) return;
    _container.innerHTML = '';

    // Action bar
    var bar = el('div', { className: 'sb-timeline-bar' });

    // Graph button (opens modal)
    var graphBtn = el('span', {
        textContent: 'Graph',
        className: 'sb-timeline-tab',
        style: { cursor: 'pointer' },
    });
    graphBtn.appendChild(iconEl('graph', 12));
    graphBtn.addEventListener('click', function() {
        events.emit('open-graph-modal');
    });
    bar.appendChild(graphBtn);

    // Spacer + action buttons
    bar.appendChild(el('span', { style: { flex: '1' } }));
    bar.appendChild(makeBtn('', 'sb-btn-ghost sb-btn-icon sb-btn-sm', function() {
        state.addStep(state.getSelectedIndex());
    }, 'add'));
    bar.appendChild(makeBtn('', 'sb-btn-ghost sb-btn-icon sb-btn-sm', function() {
        state.duplicateStep(state.getSelectedIndex());
    }, 'duplicate'));
    bar.appendChild(makeBtn('', 'sb-btn-ghost sb-btn-icon sb-btn-sm', function() {
        state.removeStep(state.getSelectedIndex());
    }, 'trash'));

    _container.appendChild(bar);

    // Timeline strip
    _strip = el('div', { className: 'sb-timeline-strip' });
    var steps = state.getSteps();
    var selectedIdx = state.getSelectedIndex();

    steps.forEach(function(step, i) {
        var thumb = el('div', {
            className: 'sb-timeline-thumb' + (i === selectedIdx ? ' sb-active' : ''),
            draggable: 'true',
        });

        // Screenshot image
        var screenshot = state.getScreenshot(i);
        if (screenshot) {
            var img = el('img', { className: 'sb-timeline-thumb-img' });
            img.src = screenshot;
            thumb.appendChild(img);
        }

        // Step number
        thumb.appendChild(el('span', { textContent: i, className: 'sb-timeline-thumb-num' }));

        // Feature icons
        var icons = el('span', { className: 'sb-timeline-thumb-icons' });
        if (step.cursor && step.cursor.from) icons.appendChild(iconEl('cursor', 10));
        if (step.highlight && step.highlight.groups && step.highlight.groups.length) icons.appendChild(iconEl('highlight', 10));
        if (step.silentClick) icons.appendChild(iconEl('silentClick', 10));
        if (step.autoNext !== undefined) icons.appendChild(iconEl('autoNext', 10));
        thumb.appendChild(icons);

        // Modified dot
        if (state.hasStepChanged(i)) {
            thumb.appendChild(el('span', { className: 'sb-timeline-thumb-modified' }));
        }

        // Capture button (appears on hover)
        var captureBtn = el('div', { className: 'sb-timeline-thumb-capture' });
        captureBtn.appendChild(iconEl('camera', 12));
        captureBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            events.emit('capture-screenshot', { index: i });
        });
        thumb.appendChild(captureBtn);

        // Click
        thumb.addEventListener('click', function() {
            state.selectStep(i);
        });

        // Drag & drop
        thumb.addEventListener('dragstart', function(e) {
            e.dataTransfer.setData('text/plain', i);
            thumb.style.opacity = '0.4';
        });
        thumb.addEventListener('dragend', function() {
            thumb.style.opacity = '';
        });
        thumb.addEventListener('dragover', function(e) {
            e.preventDefault();
            thumb.style.borderColor = '#6c8aff';
        });
        thumb.addEventListener('dragleave', function() {
            thumb.style.borderColor = '';
        });
        thumb.addEventListener('drop', function(e) {
            e.preventDefault();
            thumb.style.borderColor = '';
            var fromIdx = parseInt(e.dataTransfer.getData('text/plain'), 10);
            if (!isNaN(fromIdx) && fromIdx !== i) {
                state.moveStep(fromIdx, i);
            }
        });

        _strip.appendChild(thumb);
    });

    _container.appendChild(_strip);

    // Auto-scroll to active thumb
    setTimeout(function() {
        var activeThumb = _strip.querySelector('.sb-active');
        if (activeThumb) {
            activeThumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
    }, 50);
}
