// state.js — Central state store for Step Builder v2
// Works directly on ctx.steps (single source of truth, no sync layer)

import * as events from './events';

var state = {
    ctx: null,
    selectedIndex: 0,
    originalSteps: [],
    undoStack: [],
    redoStack: [],
    maxUndoSize: 50,
    screenshots: {},
    expandedCards: {},
    viewMode: 'timeline',
    _refreshTimer: null,
    _initialOpts: null,
};

// --- Init ---

export function init(ctx) {
    state.ctx = ctx;
    state.originalSteps = JSON.parse(JSON.stringify(ctx.steps));
    state.selectedIndex = 0;
    state.undoStack = [];
    state.redoStack = [];
    state.screenshots = {};
    state._initialOpts = {
        bubbleMaxWidth: ctx.opts.bubbleMaxWidth,
        bubbleMinWidth: ctx.opts.bubbleMinWidth,
        bubbleFontSize: ctx.opts.bubbleFontSize,
    };
}

// --- Getters ---

export function getState() { return state; }
export function getCtx() { return state.ctx; }
export function getSteps() { return state.ctx ? state.ctx.steps : []; }
export function getSelectedIndex() { return state.selectedIndex; }
export function getCurrentStep() { return state.ctx ? state.ctx.steps[state.selectedIndex] : null; }
export function getOriginalSteps() { return state.originalSteps; }
export function getInitialOpts() { return state._initialOpts; }

// --- Undo/Redo ---

function pushUndo() {
    state.undoStack.push(JSON.parse(JSON.stringify(state.ctx.steps)));
    if (state.undoStack.length > state.maxUndoSize) state.undoStack.shift();
    state.redoStack = [];
    events.emit('undo-changed', { undoSize: state.undoStack.length, redoSize: 0 });
}

export function undo() {
    if (!state.undoStack.length) return;
    state.redoStack.push(JSON.parse(JSON.stringify(state.ctx.steps)));
    var snapshot = state.undoStack.pop();
    state.ctx.steps.length = 0;
    snapshot.forEach(function(s) { state.ctx.steps.push(s); });
    state.selectedIndex = Math.min(state.selectedIndex, state.ctx.steps.length - 1);
    events.emit('state-restored');
    events.emit('undo-changed', { undoSize: state.undoStack.length, redoSize: state.redoStack.length });
    state.ctx.showStep(state.selectedIndex);
}

export function redo() {
    if (!state.redoStack.length) return;
    state.undoStack.push(JSON.parse(JSON.stringify(state.ctx.steps)));
    var snapshot = state.redoStack.pop();
    state.ctx.steps.length = 0;
    snapshot.forEach(function(s) { state.ctx.steps.push(s); });
    state.selectedIndex = Math.min(state.selectedIndex, state.ctx.steps.length - 1);
    events.emit('state-restored');
    events.emit('undo-changed', { undoSize: state.undoStack.length, redoSize: state.redoStack.length });
    state.ctx.showStep(state.selectedIndex);
}

export function getUndoSize() { return state.undoStack.length; }
export function getRedoSize() { return state.redoStack.length; }

// --- Mutations ---

export function addStep(afterIndex) {
    pushUndo();
    var newStep = { html: '', overlay: true, position: 'left', align: 'center' };
    if (arguments.length > 1 && arguments[1]) newStep._branch = arguments[1];
    state.ctx.steps.splice(afterIndex + 1, 0, newStep);
    state.selectedIndex = afterIndex + 1;
    events.emit('step-added', { index: afterIndex + 1 });
    scheduleRefresh();
}

export function removeStep(index) {
    if (state.ctx.steps.length <= 1) return;
    pushUndo();
    // Shift screenshots above the removed index
    var newScreenshots = {};
    Object.keys(state.screenshots).forEach(function(k) {
        var i = parseInt(k, 10);
        if (i < index) newScreenshots[i] = state.screenshots[i];
        else if (i > index) newScreenshots[i - 1] = state.screenshots[i];
        // i === index is dropped
    });
    state.screenshots = newScreenshots;
    state.ctx.steps.splice(index, 1);
    state.selectedIndex = Math.min(index, state.ctx.steps.length - 1);
    events.emit('step-removed', { index: index });
    scheduleRefresh();
}

export function duplicateStep(index) {
    pushUndo();
    var clone = JSON.parse(JSON.stringify(state.ctx.steps[index]));
    state.ctx.steps.splice(index + 1, 0, clone);
    state.selectedIndex = index + 1;
    events.emit('step-added', { index: index + 1 });
    scheduleRefresh();
}

export function updateStep(index, key, value) {
    pushUndo();
    state.ctx.steps[index][key] = value;
    events.emit('step-updated', { index: index, key: key });
    scheduleRefresh();
}

export function updateStepDirect(index, key, value) {
    // Update without undo push or refresh (for high-frequency updates like dragging)
    state.ctx.steps[index][key] = value;
}

export function updateStepBatch(index, updates) {
    pushUndo();
    var step = state.ctx.steps[index];
    Object.keys(updates).forEach(function(k) { step[k] = updates[k]; });
    events.emit('step-updated', { index: index, key: Object.keys(updates).join(',') });
    scheduleRefresh();
}

export function moveStep(fromIndex, toIndex) {
    if (fromIndex === toIndex) return;
    pushUndo();
    var step = state.ctx.steps.splice(fromIndex, 1)[0];
    state.ctx.steps.splice(toIndex, 0, step);
    // Remap screenshots
    var newScreenshots = {};
    Object.keys(state.screenshots).forEach(function(k) {
        var i = parseInt(k, 10);
        var newIdx = i;
        if (i === fromIndex) newIdx = toIndex;
        else if (fromIndex < toIndex && i > fromIndex && i <= toIndex) newIdx = i - 1;
        else if (fromIndex > toIndex && i >= toIndex && i < fromIndex) newIdx = i + 1;
        newScreenshots[newIdx] = state.screenshots[i];
    });
    state.screenshots = newScreenshots;
    state.selectedIndex = toIndex;
    events.emit('step-moved', { from: fromIndex, to: toIndex });
    scheduleRefresh();
}

var _showingStep = false;

// Called by user actions (step list click, arrows, etc.) — triggers showStep
export function selectStep(index) {
    if (_showingStep) return;
    var newIndex = Math.max(0, Math.min(index, state.ctx.steps.length - 1));
    state.selectedIndex = newIndex;
    events.emit('step-selected', { index: state.selectedIndex });
    _showingStep = true;
    state.ctx.showStep(state.selectedIndex);
    _showingStep = false;
}

// Called when engine already showed the step (tutorial-step-change event) — NO showStep call
export function syncSelectedIndex(index) {
    var newIndex = Math.max(0, Math.min(index, state.ctx.steps.length - 1));
    if (newIndex === state.selectedIndex) return;
    state.selectedIndex = newIndex;
    events.emit('step-selected', { index: state.selectedIndex });
}

// --- Screenshots ---

export function setScreenshot(index, dataUrl) {
    state.screenshots[index] = dataUrl;
    events.emit('screenshot-updated', { index: index });
}

export function getScreenshot(index) {
    return state.screenshots[index] || null;
}

// --- View Mode ---

export function setViewMode(mode) {
    state.viewMode = mode;
    events.emit('view-changed', { mode: mode });
}

export function getViewMode() { return state.viewMode; }

// --- Card State ---

export function setCardExpanded(cardName, expanded) {
    state.expandedCards[cardName] = expanded;
    events.emit('card-toggled', { card: cardName, expanded: expanded });
}

export function isCardExpanded(cardName) { return !!state.expandedCards[cardName]; }

// --- Change Detection ---

export function getStepChanges(index) {
    var orig = state.originalSteps[index];
    var curr = state.ctx.steps[index];
    if (!orig || !curr) return ['new step'];
    var changes = [];
    var allKeys = {};
    Object.keys(orig).forEach(function(k) { allKeys[k] = true; });
    Object.keys(curr).forEach(function(k) { allKeys[k] = true; });
    Object.keys(allKeys).forEach(function(k) {
        // Skip internal builder properties
        if (k.charAt(0) === '_' && k !== '_branch') return;
        if (k === 'advance' || k === 'autoNextDelay') return;
        var a = orig[k];
        var b = curr[k];
        var aEmpty = a === undefined || a === null || (Array.isArray(a) && a.length === 0);
        var bEmpty = b === undefined || b === null || (Array.isArray(b) && b.length === 0);
        if (aEmpty && bEmpty) return;
        if (JSON.stringify(a) !== JSON.stringify(b)) changes.push(k);
    });
    return changes;
}

export function hasStepChanged(index) {
    return getStepChanges(index).length > 0;
}

// --- Debounced Live Refresh ---

function scheduleRefresh() {
    if (state._refreshTimer) clearTimeout(state._refreshTimer);
    state._refreshTimer = setTimeout(function() {
        _showingStep = true;
        state.ctx.showStep(state.selectedIndex);
        _showingStep = false;
        state._refreshTimer = null;
    }, 300);
}

export function forceRefresh() {
    if (state._refreshTimer) clearTimeout(state._refreshTimer);
    _showingStep = true;
    state.ctx.showStep(state.selectedIndex);
    _showingStep = false;
}
