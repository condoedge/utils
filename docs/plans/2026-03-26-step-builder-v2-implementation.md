# Step Builder v2 — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Complete rewrite of the tutorial Step Builder from a single 2283-line file into a modular, Figma-inspired sidebar with card-based editing, undo/redo, drag & drop step reordering, timeline/graph views, and full coverage of all TutorialEngine properties.

**Architecture:** Modular ES6 files under `step-builder/` directory, central state working directly on `ctx.steps` (no sync layer), event bus for inter-module communication, CSS-in-JS injection. The IIFE auto-init entry point pattern is preserved exactly for backward compatibility.

**Tech Stack:** Vanilla JS (ES6 modules bundled by Laravel Mix/Webpack), SVG for icons and path editor, GSAP (available as `window.gsap`), jQuery (available as `window.$`).

**Design Document:** `docs/plans/2026-03-26-step-builder-redesign.md` — contains all visual specs, colors, spacing, card details. Reference this for any design question.

**Key files to understand before starting:**
- `resources/js/tutorials/tutorial-engine.js` — the engine the builder hooks into via `_onReady`
- `resources/js/tutorials/tutorial-step-builder.js` — the current v1 builder being replaced
- `resources/js/utils.js:90-92` — how the builder is imported (line must become `import initStepBuilder from './tutorials/step-builder/index'`)

**Critical constraints:**
- The IIFE auto-init pattern in index.js MUST match current behavior exactly
- `window.initStepBuilder` must remain the same global
- No changes to `HasIntroAnimation.php` or `intro-*.js` files
- All existing step properties must continue to work
- Dev mode (`window._tutorialDevMode`) controls all safety: no real clicks, no autoNext, no silentClick execution

---

## Task 1: Create directory structure and event bus

**Files:**
- Create: `resources/js/tutorials/step-builder/events.js`

**Step 1: Create the step-builder directory**

```bash
mkdir -p packages/condoedge/utils/resources/js/tutorials/step-builder/ui
mkdir -p packages/condoedge/utils/resources/js/tutorials/step-builder/cards
```

**Step 2: Write events.js**

```javascript
// events.js — Simple event bus for inter-module communication
// Events: step-added, step-removed, step-updated, step-moved, step-selected,
//         state-restored, view-changed, card-toggled

var _listeners = {};

export function on(event, callback) {
    if (!_listeners[event]) _listeners[event] = [];
    _listeners[event].push(callback);
}

export function off(event, callback) {
    if (!_listeners[event]) return;
    _listeners[event] = _listeners[event].filter(function(cb) { return cb !== callback; });
}

export function emit(event, data) {
    if (!_listeners[event]) return;
    _listeners[event].forEach(function(cb) {
        try { cb(data); } catch(e) { console.warn('StepBuilder event error:', event, e); }
    });
}
```

**Step 3: Commit**

```bash
git add packages/condoedge/utils/resources/js/tutorials/step-builder/events.js
git commit -m "feat(step-builder): create directory structure and event bus"
```

---

## Task 2: Create state management module

**Files:**
- Create: `resources/js/tutorials/step-builder/state.js`
- Reference: `docs/plans/2026-03-26-step-builder-redesign.md` §2

**Step 1: Write state.js**

```javascript
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

export function selectStep(index) {
    state.selectedIndex = Math.max(0, Math.min(index, state.ctx.steps.length - 1));
    events.emit('step-selected', { index: state.selectedIndex });
    state.ctx.showStep(state.selectedIndex);
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
        state.ctx.showStep(state.selectedIndex);
        state._refreshTimer = null;
    }, 300);
}

export function forceRefresh() {
    if (state._refreshTimer) clearTimeout(state._refreshTimer);
    state.ctx.showStep(state.selectedIndex);
}
```

**Step 2: Commit**

```bash
git add packages/condoedge/utils/resources/js/tutorials/step-builder/state.js
git commit -m "feat(step-builder): add central state management with undo/redo"
```

---

## Task 3: Create styles module with CSS variables

**Files:**
- Create: `resources/js/tutorials/step-builder/styles.js`
- Reference: `docs/plans/2026-03-26-step-builder-redesign.md` §16

**Step 1: Write styles.js**

This file contains ALL CSS for the Step Builder, injected via a `<style>` tag scoped with `[data-sb]`.

```javascript
// styles.js — CSS injection for Step Builder v2
// All styles scoped via [data-sb] attribute

export function injectStyles() {
    var styleEl = document.createElement('style');
    styleEl.setAttribute('data-sb-styles', '');
    styleEl.textContent = CSS_TEXT;
    document.head.appendChild(styleEl);
    return styleEl;
}

var CSS_TEXT = [

    // === RESET & BASE ===
    '[data-sb] { font-family: system-ui, -apple-system, sans-serif; font-size: 13px; color: #e0e4ec; line-height: 1.5; }',
    '[data-sb] *, [data-sb] *::before, [data-sb] *::after { box-sizing: border-box; margin: 0; padding: 0; }',

    // === SCROLLBAR ===
    '[data-sb] ::-webkit-scrollbar { width: 5px; height: 5px; }',
    '[data-sb] ::-webkit-scrollbar-track { background: transparent; }',
    '[data-sb] ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }',
    '[data-sb] ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }',

    // === SIDEBAR ===
    '[data-sb].sb-sidebar { position: fixed; top: 0; right: 0; width: 420px; height: 100vh; z-index: 99997;' +
    '  background: linear-gradient(170deg, #1e1e2e 0%, #161625 100%);' +
    '  border-left: 1px solid rgba(255,255,255,0.06);' +
    '  box-shadow: -4px 0 24px rgba(0,0,0,0.4);' +
    '  display: flex; flex-direction: column; overflow: hidden;' +
    '  transition: width 0.2s ease; }',
    '[data-sb].sb-sidebar.sb-collapsed { width: 0; border: none; overflow: hidden; }',

    // === RESIZE HANDLE (left edge) ===
    '[data-sb] .sb-resize-left { position: absolute; width: 6px; top: 0; bottom: 0; left: 0; cursor: ew-resize; z-index: 2; }',
    '[data-sb] .sb-resize-left:hover { background: rgba(108,138,255,0.2); }',

    // === HEADER ===
    '[data-sb] .sb-header {' +
    '  height: 48px; min-height: 48px; padding: 0 14px; display: flex; align-items: center; gap: 8px;' +
    '  border-bottom: 1px solid rgba(255,255,255,0.06); background: rgba(255,255,255,0.02);' +
    '  user-select: none; flex-shrink: 0; }',
    '[data-sb] .sb-header-title { font-size: 14px; font-weight: 700; flex: 1; }',
    '[data-sb] .sb-header-icon { width: 16px; height: 16px; color: #6c8aff; opacity: 0.6; }',

    // === INPUTS ===
    '[data-sb] input, [data-sb] select, [data-sb] textarea {' +
    '  background: rgba(255,255,255,0.06); color: #e0e4ec; border: 1px solid rgba(255,255,255,0.1);' +
    '  border-radius: 8px; padding: 8px 10px; font-size: 13px; font-family: inherit;' +
    '  transition: border-color 0.2s, box-shadow 0.2s; outline: none; width: 100%; }',
    '[data-sb] input:focus, [data-sb] select:focus, [data-sb] textarea:focus {' +
    '  border-color: #6c8aff; box-shadow: 0 0 0 2px rgba(108,138,255,0.15); }',
    '[data-sb] input[type="number"] { width: 72px; }',
    '[data-sb] input[type="checkbox"] { width: 16px; height: 16px; accent-color: #6c8aff; cursor: pointer; }',
    '[data-sb] select { cursor: pointer; appearance: none; padding-right: 24px;' +
    '  background-image: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'10\' height=\'6\'%3E%3Cpath d=\'M0 0l5 6 5-6z\' fill=\'%23888\'/%3E%3C/svg%3E");' +
    '  background-repeat: no-repeat; background-position: right 8px center; }',

    // === BUTTONS ===
    '[data-sb] .sb-btn {' +
    '  border: none; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600;' +
    '  padding: 6px 12px; transition: all 0.15s; font-family: inherit; white-space: nowrap;' +
    '  display: inline-flex; align-items: center; gap: 4px; }',
    '[data-sb] .sb-btn:hover { filter: brightness(1.15); transform: translateY(-1px); }',
    '[data-sb] .sb-btn:active { transform: translateY(0); }',
    '[data-sb] .sb-btn-primary { background: #6c8aff; color: #fff; }',
    '[data-sb] .sb-btn-green { background: #2ecc71; color: #fff; }',
    '[data-sb] .sb-btn-red { background: #e74c3c; color: #fff; }',
    '[data-sb] .sb-btn-yellow { background: #f1c40f; color: #1a1a2e; }',
    '[data-sb] .sb-btn-ghost { background: rgba(255,255,255,0.06); color: #ccc; border: 1px solid rgba(255,255,255,0.1); }',
    '[data-sb] .sb-btn-ghost:hover { background: rgba(255,255,255,0.12); }',
    '[data-sb] .sb-btn-sm { padding: 4px 8px; font-size: 11px; }',
    '[data-sb] .sb-btn-icon { padding: 6px; min-width: 28px; justify-content: center; }',

    // === CARDS ===
    '[data-sb] .sb-card {' +
    '  background: #2a2a3d; border: 1px solid rgba(255,255,255,0.08); border-radius: 8px;' +
    '  margin-bottom: 8px; overflow: hidden; transition: all 0.2s ease; }',
    '[data-sb] .sb-card.sb-card-active { box-shadow: 0 2px 8px rgba(0,0,0,0.3); }',
    '[data-sb] .sb-card-header {' +
    '  display: flex; align-items: center; gap: 8px; padding: 8px 12px; cursor: pointer;' +
    '  user-select: none; }',
    '[data-sb] .sb-card-header:hover { background: rgba(255,255,255,0.03); }',
    '[data-sb] .sb-card-icon { width: 16px; height: 16px; color: #6c8aff; flex-shrink: 0; }',
    '[data-sb] .sb-card-title { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; color: #6c8aff; flex: 1; }',
    '[data-sb] .sb-card-toggle {' +
    '  width: 32px; height: 18px; border-radius: 9px; background: rgba(255,255,255,0.15);' +
    '  position: relative; cursor: pointer; transition: background 0.2s; flex-shrink: 0; }',
    '[data-sb] .sb-card-toggle.sb-on { background: #6c8aff; }',
    '[data-sb] .sb-card-toggle::after {' +
    '  content: ""; position: absolute; width: 14px; height: 14px; border-radius: 50%;' +
    '  background: #fff; top: 2px; left: 2px; transition: transform 0.2s; }',
    '[data-sb] .sb-card-toggle.sb-on::after { transform: translateX(14px); }',
    '[data-sb] .sb-card-body { padding: 8px 12px 12px; }',
    '[data-sb] .sb-card-collapsed .sb-card-body { display: none; }',
    '[data-sb] .sb-card-off { opacity: 0.5; }',
    '[data-sb] .sb-card-off .sb-card-body { display: none; }',

    // === ROWS & LABELS ===
    '[data-sb] .sb-row { display: flex; gap: 6px; align-items: center; margin-bottom: 8px; flex-wrap: wrap; }',
    '[data-sb] .sb-label { font-size: 11px; color: #7a7f8e; min-width: 50px; flex-shrink: 0; text-transform: uppercase; letter-spacing: 0.3px; }',
    '[data-sb] .sb-selector { color: #2ecc71; font-size: 11px; word-break: break-all; font-family: "Fira Code", "Cascadia Code", monospace; }',
    '[data-sb] .sb-badge { display: inline-block; font-size: 10px; padding: 2px 6px; border-radius: 4px; background: rgba(108,138,255,0.15); color: #6c8aff; font-weight: 600; }',

    // === STEP LIST ===
    '[data-sb] .sb-step-list { border-bottom: 1px solid rgba(255,255,255,0.06); padding: 8px 14px; }',
    '[data-sb] .sb-step-list-header {' +
    '  display: flex; align-items: center; justify-content: space-between; padding: 4px 0;' +
    '  cursor: pointer; user-select: none; }',
    '[data-sb] .sb-step-list-header-title { font-size: 11px; font-weight: 600; color: #7a7f8e; text-transform: uppercase; letter-spacing: 0.3px; }',
    '[data-sb] .sb-step-item {' +
    '  display: flex; align-items: center; gap: 6px; padding: 6px 8px; border-radius: 6px;' +
    '  cursor: pointer; transition: background 0.15s; font-size: 12px; }',
    '[data-sb] .sb-step-item:hover { background: rgba(108,138,255,0.1); }',
    '[data-sb] .sb-step-item.sb-active { background: rgba(108,138,255,0.2); border: 1px solid rgba(108,138,255,0.3); }',
    '[data-sb] .sb-step-item-num { width: 22px; height: 22px; border-radius: 50%; background: rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex-shrink: 0; }',
    '[data-sb] .sb-step-item.sb-active .sb-step-item-num { background: #6c8aff; color: #fff; }',
    '[data-sb] .sb-step-item-text { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #b0b4c0; }',
    '[data-sb] .sb-step-item-icons { display: flex; gap: 2px; flex-shrink: 0; }',
    '[data-sb] .sb-step-item-icons svg { width: 12px; height: 12px; color: #7a7f8e; }',
    '[data-sb] .sb-step-item-modified { width: 6px; height: 6px; border-radius: 50%; background: #f1c40f; flex-shrink: 0; }',
    '[data-sb] .sb-step-item.sb-dragging { opacity: 0.4; }',

    // === DETAIL AREA ===
    '[data-sb] .sb-detail { flex: 1; overflow-y: auto; padding: 10px 14px; }',

    // === TIMELINE ===
    '[data-sb] .sb-timeline { height: 180px; min-height: 180px; border-top: 1px solid rgba(255,255,255,0.06); display: flex; flex-direction: column; flex-shrink: 0; }',
    '[data-sb] .sb-timeline-strip { flex: 1; display: flex; gap: 6px; overflow-x: auto; padding: 8px 14px; align-items: stretch; }',
    '[data-sb] .sb-timeline-thumb {' +
    '  width: 80px; min-width: 80px; border-radius: 6px; border: 2px solid rgba(255,255,255,0.08);' +
    '  background: linear-gradient(135deg, #2a2a3d, #1e1e2e); cursor: pointer;' +
    '  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px;' +
    '  transition: border-color 0.15s; position: relative; overflow: hidden; }',
    '[data-sb] .sb-timeline-thumb:hover { border-color: rgba(108,138,255,0.3); }',
    '[data-sb] .sb-timeline-thumb.sb-active { border-color: #6c8aff; box-shadow: 0 0 8px rgba(108,138,255,0.3); }',
    '[data-sb] .sb-timeline-thumb-num { font-size: 14px; font-weight: 700; color: #7a7f8e; z-index: 1; }',
    '[data-sb] .sb-timeline-thumb.sb-active .sb-timeline-thumb-num { color: #6c8aff; }',
    '[data-sb] .sb-timeline-thumb-img { position: absolute; inset: 0; object-fit: cover; opacity: 0.3; }',
    '[data-sb] .sb-timeline-thumb-icons { display: flex; gap: 2px; z-index: 1; }',
    '[data-sb] .sb-timeline-thumb-icons svg { width: 10px; height: 10px; color: #7a7f8e; }',
    '[data-sb] .sb-timeline-thumb-modified { position: absolute; top: 4px; right: 4px; width: 6px; height: 6px; border-radius: 50%; background: #f1c40f; }',
    '[data-sb] .sb-timeline-thumb-capture {' +
    '  position: absolute; top: 4px; left: 4px; width: 18px; height: 18px; border-radius: 4px;' +
    '  background: rgba(0,0,0,0.5); cursor: pointer; display: none; align-items: center; justify-content: center; }',
    '[data-sb] .sb-timeline-thumb:hover .sb-timeline-thumb-capture { display: flex; }',
    '[data-sb] .sb-timeline-bar {' +
    '  display: flex; align-items: center; gap: 8px; padding: 6px 14px;' +
    '  border-top: 1px solid rgba(255,255,255,0.06); }',
    '[data-sb] .sb-timeline-tab {' +
    '  font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 6px;' +
    '  cursor: pointer; color: #7a7f8e; transition: all 0.15s; }',
    '[data-sb] .sb-timeline-tab:hover { color: #e0e4ec; background: rgba(255,255,255,0.06); }',
    '[data-sb] .sb-timeline-tab.sb-active { color: #6c8aff; background: rgba(108,138,255,0.15); }',

    // === HISTORY DRAWER ===
    '[data-sb] .sb-history-drawer {' +
    '  position: absolute; top: 48px; left: 0; bottom: 180px; width: 200px;' +
    '  background: #1a1a2e; border-right: 1px solid rgba(255,255,255,0.08);' +
    '  transform: translateX(-100%); transition: transform 0.2s ease;' +
    '  overflow-y: auto; z-index: 3; }',
    '[data-sb] .sb-history-drawer.sb-open { transform: translateX(0); }',
    '[data-sb] .sb-history-item {' +
    '  display: flex; align-items: center; gap: 6px; padding: 6px 10px; font-size: 11px;' +
    '  color: #7a7f8e; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.04); }',
    '[data-sb] .sb-history-item:hover { background: rgba(108,138,255,0.1); color: #e0e4ec; }',
    '[data-sb] .sb-history-item.sb-current { color: #6c8aff; background: rgba(108,138,255,0.08); }',

    // === ELEMENT PICKER OVERLAY ===
    '[data-sb] .sb-picker-info {' +
    '  position: fixed; z-index: 100002; pointer-events: none; font-family: "Fira Code", monospace; font-size: 11px; }',
    '[data-sb] .sb-picker-selector { background: #1e1e2e; color: #6c8aff; padding: 3px 8px; border-radius: 4px; white-space: nowrap; }',
    '[data-sb] .sb-picker-dimensions { background: #1e1e2e; color: #f1c40f; padding: 2px 6px; border-radius: 4px; font-size: 10px; margin-left: 6px; }',
    '[data-sb] .sb-picker-breadcrumb {' +
    '  background: #1e1e2e; color: #7a7f8e; padding: 3px 8px; border-radius: 4px;' +
    '  font-size: 10px; margin-top: 2px; white-space: nowrap; }',
    '[data-sb] .sb-picker-outline {' +
    '  position: fixed; border: 2px solid #6c8aff; border-radius: 4px; pointer-events: none;' +
    '  z-index: 100001; transition: all 0.08s ease; box-shadow: 0 0 0 3px rgba(108,138,255,0.2); }',

    // === CODE OUTPUT ===
    '[data-sb] .sb-code {' +
    '  font-family: "Fira Code", "Cascadia Code", "JetBrains Mono", monospace;' +
    '  background: rgba(0,0,0,0.3); padding: 10px 12px; border-radius: 8px; font-size: 10px;' +
    '  overflow-x: auto; max-height: 200px; white-space: pre-wrap; color: #8bc78b;' +
    '  border: 1px solid rgba(255,255,255,0.04); line-height: 1.6; }',

    // === CHECKBOX LABEL ===
    '[data-sb] .sb-checkbox { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; color: #b0b4c0; cursor: pointer; }',

    // === GRAPH VIEW ===
    '[data-sb] .sb-graph { flex: 1; overflow: auto; padding: 8px; position: relative; }',
    '[data-sb] .sb-graph-node {' +
    '  display: inline-flex; align-items: center; gap: 4px; padding: 6px 10px; border-radius: 6px;' +
    '  background: #2a2a3d; border: 1px solid rgba(255,255,255,0.08); font-size: 11px;' +
    '  cursor: pointer; position: absolute; transition: border-color 0.15s; }',
    '[data-sb] .sb-graph-node:hover { border-color: rgba(108,138,255,0.3); }',
    '[data-sb] .sb-graph-node.sb-active { border-color: #6c8aff; background: rgba(108,138,255,0.15); }',
    '[data-sb] .sb-graph-node.sb-diamond { transform: rotate(45deg); padding: 4px; }',
    '[data-sb] .sb-graph-node.sb-diamond > * { transform: rotate(-45deg); }',

    // === FLOATING BADGES (for dev mode) ===
    '.sb-dev-badge {' +
    '  position: fixed; z-index: 99999; background: rgba(108,138,255,0.9); color: #fff;' +
    '  padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600;' +
    '  font-family: system-ui, sans-serif; pointer-events: none;' +
    '  box-shadow: 0 2px 8px rgba(0,0,0,0.3); }',

    // === SPECTATOR EXIT BUTTON ===
    '.sb-spectator-exit {' +
    '  position: fixed; top: 16px; right: 16px; z-index: 100001;' +
    '  background: rgba(0,0,0,0.7); color: #fff; border: 1px solid rgba(255,255,255,0.2);' +
    '  border-radius: 8px; padding: 8px 16px; font-size: 13px; font-weight: 600;' +
    '  cursor: pointer; backdrop-filter: blur(8px); transition: opacity 0.2s; }',
    '.sb-spectator-exit:hover { opacity: 1; }',

    // === RESPONSIVE ===
    '@media (max-width: 600px) {' +
    '  [data-sb].sb-sidebar { width: 100vw; }' +
    '  [data-sb] input, [data-sb] select { font-size: 14px; padding: 8px; }' +
    '  [data-sb] .sb-btn { font-size: 12px; padding: 6px 10px; }' +
    '}',

].join('\n');
```

**Step 2: Commit**

```bash
git add packages/condoedge/utils/resources/js/tutorials/step-builder/styles.js
git commit -m "feat(step-builder): add CSS injection module with Figma-inspired styles"
```

---

## Task 4: Create SVG icons module

**Files:**
- Create: `resources/js/tutorials/step-builder/icons.js`

**Step 1: Write icons.js**

All icons as inline SVG strings, 16×16, stroke-based, 1.5px stroke.

```javascript
// icons.js — SVG icons for Step Builder (no emojis)
// Each function returns an SVG string. Use by setting innerHTML.

export var ICONS = {
    cursor: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><line x1="8" y1="1" x2="8" y2="15"/><line x1="1" y1="8" x2="15" y2="8"/><circle cx="8" cy="8" r="5"/></svg>',

    highlight: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M8 1L10 6H15L11 9.5L12.5 15L8 11.5L3.5 15L5 9.5L1 6H6L8 1Z"/></svg>',

    options: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><line x1="3" y1="4" x2="13" y2="4"/><line x1="3" y1="8" x2="13" y2="8"/><line x1="3" y1="12" x2="13" y2="12"/><circle cx="3" cy="4" r="1" fill="currentColor"/><circle cx="3" cy="8" r="1" fill="currentColor"/><circle cx="3" cy="12" r="1" fill="currentColor"/></svg>',

    autoNext: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M2 3L8 8L2 13Z"/><path d="M8 3L14 8L8 13Z"/></svg>',

    hover: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M4 1V10L6.5 7.5L9 13L11 12L8.5 6L12 6L4 1Z"/></svg>',

    scroll: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><line x1="8" y1="1" x2="8" y2="13"/><polyline points="4 9 8 13 12 9"/></svg>',

    silentClick: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-dasharray="2 2"><circle cx="8" cy="8" r="6"/><path d="M8 5V8L10 10"/></svg>',

    linkNext: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M6 4H4C2.9 4 2 4.9 2 6V10C2 11.1 2.9 12 4 12H6"/><path d="M10 4H12C13.1 4 14 4.9 14 6V10C14 11.1 13.1 12 12 12H10"/><line x1="5" y1="8" x2="11" y2="8"/></svg>',

    conditions: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M8 2L14 8L8 14L2 8Z"/></svg>',

    actions: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M9 2L4 9H8L7 14L12 7H8L9 2Z"/></svg>',

    advance: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><polyline points="6 5 10 8 6 11"/></svg>',

    position: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="2" y="2" width="12" height="12" rx="2"/><line x1="8" y1="2" x2="8" y2="14"/><line x1="2" y1="8" x2="14" y2="8"/></svg>',

    content: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="2" y="2" width="12" height="12" rx="2"/><line x1="5" y1="5" x2="11" y2="5"/><line x1="5" y1="8" x2="11" y2="8"/><line x1="5" y1="11" x2="9" y2="11"/></svg>',

    settings: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="2.5"/><path d="M8 1V3M8 13V15M1 8H3M13 8H15M2.9 2.9L4.3 4.3M11.7 11.7L13.1 13.1M13.1 2.9L11.7 4.3M4.3 11.7L2.9 13.1"/></svg>',

    menu: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><line x1="2" y1="4" x2="14" y2="4"/><line x1="2" y1="8" x2="14" y2="8"/><line x1="2" y1="12" x2="14" y2="12"/></svg>',

    collapse: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><line x1="4" y1="8" x2="12" y2="8"/></svg>',

    expand: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><line x1="4" y1="8" x2="12" y2="8"/><line x1="8" y1="4" x2="8" y2="12"/></svg>',

    undo: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M4 7H10C12.2 7 14 8.8 14 11C14 13.2 12.2 15 10 15H8"/><polyline points="7 4 4 7 7 10"/></svg>',

    redo: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M12 7H6C3.8 7 2 8.8 2 11C2 13.2 3.8 15 6 15H8"/><polyline points="9 4 12 7 9 10"/></svg>',

    history: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><polyline points="8 4 8 8 11 10"/></svg>',

    camera: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="1" y="4" width="14" height="10" rx="2"/><circle cx="8" cy="9" r="3"/><path d="M5 4L6 2H10L11 4"/></svg>',

    trash: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><polyline points="3 4 4 14 12 14 13 4"/><line x1="2" y1="4" x2="14" y2="4"/><path d="M6 4V2H10V4"/></svg>',

    copy: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="5" y="5" width="9" height="9" rx="1.5"/><path d="M5 11H3.5C2.7 11 2 10.3 2 9.5V3.5C2 2.7 2.7 2 3.5 2H9.5C10.3 2 11 2.7 11 3.5V5"/></svg>',

    duplicate: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="4" y="4" width="10" height="10" rx="1.5"/><path d="M4 12H3C2.4 12 2 11.6 2 11V3C2 2.4 2.4 2 3 2H11C11.6 2 12 2.4 12 3V4"/><line x1="9" y1="7" x2="9" y2="11"/><line x1="7" y1="9" x2="11" y2="9"/></svg>',

    spectator: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><ellipse cx="8" cy="8" rx="7" ry="4"/><circle cx="8" cy="8" r="2"/></svg>',

    play: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="4,2 14,8 4,14" fill="currentColor"/></svg>',

    close: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><line x1="4" y1="4" x2="12" y2="12"/><line x1="12" y1="4" x2="4" y2="12"/></svg>',

    chevronDown: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><polyline points="4 6 8 10 12 6"/></svg>',

    chevronRight: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><polyline points="6 4 10 8 6 12"/></svg>',

    add: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><line x1="8" y1="3" x2="8" y2="13"/><line x1="3" y1="8" x2="13" y2="8"/></svg>',

    pick: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="3"/><line x1="8" y1="1" x2="8" y2="4"/><line x1="8" y1="12" x2="8" y2="15"/><line x1="1" y1="8" x2="4" y2="8"/><line x1="12" y1="8" x2="15" y2="8"/></svg>',

    graph: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="3" cy="8" r="2"/><circle cx="13" cy="4" r="2"/><circle cx="13" cy="12" r="2"/><line x1="5" y1="7" x2="11" y2="4.5"/><line x1="5" y1="9" x2="11" y2="11.5"/></svg>',

    timeline: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="1" y="4" width="4" height="8" rx="1"/><rect x="6" y="4" width="4" height="8" rx="1"/><rect x="11" y="4" width="4" height="8" rx="1"/></svg>',
};

// Helper: create a DOM element with icon innerHTML
export function iconEl(name, size) {
    var span = document.createElement('span');
    span.innerHTML = ICONS[name] || '';
    span.style.display = 'inline-flex';
    span.style.alignItems = 'center';
    span.style.justifyContent = 'center';
    if (size) {
        span.style.width = size + 'px';
        span.style.height = size + 'px';
    }
    return span;
}
```

**Step 2: Commit**

```bash
git add packages/condoedge/utils/resources/js/tutorials/step-builder/icons.js
git commit -m "feat(step-builder): add SVG icon library (no emojis)"
```

---

## Task 5: Create DOM helpers module

**Files:**
- Create: `resources/js/tutorials/step-builder/helpers.js`

**Step 1: Write helpers.js**

Shared DOM creation helpers used by all UI modules. Ported from v1 `el()`, `makeBtn()`, etc. but adapted for new class names.

```javascript
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
```

**Step 2: Commit**

```bash
git add packages/condoedge/utils/resources/js/tutorials/step-builder/helpers.js
git commit -m "feat(step-builder): add DOM helper utilities"
```

---

## Task 6: Create code generation module

**Files:**
- Create: `resources/js/tutorials/step-builder/codegen.js`
- Reference: `docs/plans/2026-03-26-step-builder-redesign.md` §15

**Step 1: Write codegen.js**

Complete code generation that exports ALL properties including new ones (hideIf, linkNext, redirect, cursor.delay, cursor.image, highlight.padding/borderRadius, _branch). Strips internal `_` props (except `_branch`).

```javascript
// codegen.js — Code generation for Step Builder v2
// Exports ALL step properties, strips internal _ prefixed props (except _branch)

import * as state from './state';

function escStr(s) {
    return String(s || '').replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/'/g, "\\'");
}

export function generateStepCode(s) {
    var lines = [];
    lines.push('{');

    // silentClick-only step (shorthand)
    if (s.silentClick && !s.html) {
        lines.push('    silentClick: \'' + escStr(s.silentClick) + '\',');
        if (s._branch) lines.push('    _branch: \'' + escStr(s._branch) + '\',');
        lines.push('}');
        return lines.join('\n');
    }

    // Branch
    if (s._branch) lines.push('    _branch: \'' + escStr(s._branch) + '\',');

    // Content
    if (s.html) lines.push('    html: \'' + escStr(s.html) + '\',');
    if (s.overlay === false) lines.push('    overlay: false,');
    if (s.clearOverlay) lines.push('    clearOverlay: true,');

    // Back button
    if (typeof s.showBack === 'number') lines.push('    showBack: ' + s.showBack + ',');
    else if (s.showBack) lines.push('    showBack: true,');

    // Position
    if (s.position && s.position !== 'left') lines.push('    position: \'' + s.position + '\',');
    if (s.align && s.align !== 'center') lines.push('    align: \'' + s.align + '\',');
    if (s.chatMode) lines.push('    chatMode: true,');
    if (s.chatMaxWidth) lines.push('    chatMaxWidth: \'' + escStr(s.chatMaxWidth) + '\',');
    if (s.positionTarget) lines.push('    positionTarget: \'' + escStr(s.positionTarget) + '\',');

    // Conditions (NEW)
    if (s.showIf) lines.push('    showIf: \'' + escStr(s.showIf) + '\',');
    if (s.hideIf) lines.push('    hideIf: \'' + escStr(s.hideIf) + '\',');

    // Actions (NEW)
    if (s.silentClick) lines.push('    silentClick: \'' + escStr(s.silentClick) + '\',');
    if (s.linkNext) lines.push('    linkNext: \'' + escStr(s.linkNext) + '\',');
    if (s.redirect) lines.push('    redirect: \'' + escStr(s.redirect) + '\',');

    // Cursor
    if (s.cursor && s.cursor.from) {
        lines.push('    cursor: {');
        lines.push('        from: \'' + escStr(s.cursor.from) + '\',');
        if (s.cursor.fromAnchor) lines.push('        fromAnchor: { x: ' + s.cursor.fromAnchor.x + ', y: ' + s.cursor.fromAnchor.y + ' },');
        if (s.cursor.to) {
            lines.push('        to: \'' + escStr(s.cursor.to) + '\',');
            if (s.cursor.toAnchor) lines.push('        toAnchor: { x: ' + s.cursor.toAnchor.x + ', y: ' + s.cursor.toAnchor.y + ' },');
        }
        if (s.cursor.svgPath && !s.cursor.waypoints) lines.push('        svgPath: \'' + escStr(s.cursor.svgPath) + '\',');
        if (s.cursor.duration && s.cursor.duration !== 1.5) lines.push('        duration: ' + s.cursor.duration + ',');
        if (s.cursor.delay && s.cursor.delay > 0) lines.push('        delay: ' + s.cursor.delay + ',');
        if (s.cursor.ease && s.cursor.ease !== 'power2.inOut') lines.push('        ease: \'' + s.cursor.ease + '\',');
        if (s.cursor.click) lines.push('        click: true,');
        if (s.cursor.loop) lines.push('        loop: true,');
        if (s.cursor.image) lines.push('        image: \'' + escStr(s.cursor.image) + '\',');
        if (s.cursor.waypoints && s.cursor.waypoints.length) {
            lines.push('        waypoints: [');
            s.cursor.waypoints.forEach(function(wp) {
                var parts = ['target: \'' + escStr(wp.target) + '\''];
                if (wp.svgPath) parts.push('svgPath: \'' + escStr(wp.svgPath) + '\'');
                if (wp.action && wp.action !== 'path') parts.push('action: \'' + wp.action + '\'');
                if (wp.pause && wp.pause > 0) parts.push('pause: ' + wp.pause);
                lines.push('            { ' + parts.join(', ') + ' },');
            });
            lines.push('        ],');
        }
        lines.push('    },');
    }

    // Highlight
    if (s.highlight && s.highlight.groups && s.highlight.groups.length) {
        lines.push('    highlight: {');
        lines.push('        groups: [');
        s.highlight.groups.forEach(function(g) {
            var elems = g.elements || g;
            if (Array.isArray(elems)) {
                lines.push('            [\'' + elems.map(escStr).join('\', \'') + '\'],');
            }
        });
        lines.push('        ],');
        lines.push('        padding: ' + (s.highlight.padding || 8) + ',');
        lines.push('        borderRadius: ' + (s.highlight.borderRadius || 8) + ',');
        if (s.highlight.blockOutside) lines.push('        blockOutside: true,');
        lines.push('    },');
    }

    // Hover
    if (s.hover) {
        var hList = Array.isArray(s.hover) ? s.hover : [s.hover];
        if (hList.length === 1) {
            lines.push('    hover: \'' + escStr(hList[0]) + '\',');
        } else if (hList.length > 1) {
            lines.push('    hover: [\'' + hList.map(escStr).join('\', \'') + '\'],');
        }
    }

    // Scroll
    if (s.scrollTo) lines.push('    scrollTo: \'' + escStr(s.scrollTo) + '\',');
    if (s.scrollInside && s.scrollInside.selector) {
        lines.push('    scrollInside: {');
        lines.push('        selector: \'' + escStr(s.scrollInside.selector) + '\',');
        if (s.scrollInside.to !== undefined) lines.push('        to: ' + s.scrollInside.to + ',');
        if (s.scrollInside.by !== undefined) lines.push('        by: ' + s.scrollInside.by + ',');
        if (s.scrollInside.duration) lines.push('        duration: ' + s.scrollInside.duration + ',');
        lines.push('    },');
    }

    // Options
    if (s.options && s.options.length) {
        lines.push('    options: [');
        s.options.forEach(function(opt) {
            var parts = [];
            if (opt.label) parts.push('label: \'' + escStr(opt.label) + '\'');
            if (opt.done) parts.push('done: true');
            else if (opt.redirect !== undefined) {
                parts.push('redirect: \'' + escStr(opt.redirect) + '\'');
                if (opt.startTutorial) parts.push('startTutorial: true');
            } else if (opt.goToStep !== undefined) parts.push('goToStep: ' + opt.goToStep);
            lines.push('        { ' + parts.join(', ') + ' },');
        });
        lines.push('    ],');
    }

    // Advance
    if (s.autoNext !== undefined) {
        lines.push('    autoNext: ' + (typeof s.autoNext === 'number' ? s.autoNext : 3) + ',');
    } else if (s.afterAnimation) {
        lines.push('    afterAnimation: true,');
    }

    lines.push('}');
    return lines.join('\n');
}

export function generateFullCode() {
    var steps = state.getSteps();
    var ctx = state.getCtx();
    var initialOpts = state.getInitialOpts();
    var lines = [];

    lines.push('$(document).ready(function(){');
    lines.push('');
    lines.push('    var steps = [');
    steps.forEach(function(s, i) {
        lines.push('        // Step ' + i);
        var stepCode = generateStepCode(s);
        var stepLines = stepCode.split('\n');
        stepLines.forEach(function(l) { lines.push('        ' + l); });
        var last = lines.length - 1;
        if (lines[last].trim() === '}') lines[last] = lines[last] + ',';
    });
    lines.push('    ];');
    lines.push('');
    lines.push('    TutorialEngine.start(steps, {');
    lines.push('        nextLabel: \'tutorial.next\',');
    lines.push('        doneLabel: \'tutorial.done\',');
    if (ctx.opts.bubbleMaxWidth !== initialOpts.bubbleMaxWidth) {
        lines.push('        bubbleMaxWidth: \'' + ctx.opts.bubbleMaxWidth + '\',');
        lines.push('        bubbleMinWidth: \'' + ctx.opts.bubbleMinWidth + '\',');
    }
    if (ctx.opts.bubbleFontSize !== initialOpts.bubbleFontSize) {
        lines.push('        bubbleFontSize: \'' + ctx.opts.bubbleFontSize + '\',');
    }
    lines.push('    });');
    lines.push('');
    lines.push('});');
    return lines.join('\n');
}

export function generateCopyAllText() {
    var steps = state.getSteps();
    var pagePath = window.location.pathname;
    var allChanges = [];
    steps.forEach(function(s, i) {
        var changes = state.getStepChanges(i);
        if (changes.length) allChanges.push('Step ' + i + ': ' + changes.join(', '));
    });
    var parts = ['/tutorial-creator', '', 'Page: ' + pagePath];
    if (allChanges.length) {
        parts.push('Changes:');
        allChanges.forEach(function(c) { parts.push('  - ' + c); });
    }
    parts.push('');
    parts.push(generateFullCode());
    return parts.join('\n');
}

export function generateCopyStepText(index) {
    var steps = state.getSteps();
    var s = steps[index];
    if (!s) return '';
    var pagePath = window.location.pathname;
    var branch = s._branch || '';
    var changes = state.getStepChanges(index);
    var parts = ['/tutorial-creator', '', 'Page: ' + pagePath];
    parts.push('Step ' + index + (branch ? ' (branch: ' + branch + ')' : ''));
    if (changes.length) parts.push('Changed: ' + changes.join(', '));
    parts.push('');
    parts.push(generateStepCode(s));
    return parts.join('\n');
}

export function generateCopyChangedText() {
    var steps = state.getSteps();
    var pagePath = window.location.pathname;
    var changedSteps = [];
    steps.forEach(function(s, i) {
        var changes = state.getStepChanges(i);
        if (changes.length) changedSteps.push(i);
    });
    if (!changedSteps.length) return 'No changes';
    var parts = ['/tutorial-creator', '', 'Page: ' + pagePath];
    parts.push('Changed steps: ' + changedSteps.join(', '));
    parts.push('');
    changedSteps.forEach(function(i) {
        var s = steps[i];
        var branch = s._branch || '';
        var changes = state.getStepChanges(i);
        parts.push('Step ' + i + (branch ? ' (branch: ' + branch + ')' : '') + ' — Changed: ' + changes.join(', '));
        parts.push(generateStepCode(s));
        parts.push('');
    });
    return parts.join('\n');
}
```

**Step 2: Commit**

```bash
git add packages/condoedge/utils/resources/js/tutorials/step-builder/codegen.js
git commit -m "feat(step-builder): add code generation with all properties including new ones"
```

---

## Task 7: Create keyboard shortcuts module

**Files:**
- Create: `resources/js/tutorials/step-builder/shortcuts.js`
- Reference: `docs/plans/2026-03-26-step-builder-redesign.md` §12

**Step 1: Write shortcuts.js**

```javascript
// shortcuts.js — Keyboard shortcuts for Step Builder v2
// Only active when builder is visible and no input is focused

import * as state from './state';
import * as events from './events';
import * as codegen from './codegen';
import { copyToClipboard } from './helpers';

var _active = false;
var _panel = null;

function isInputFocused() {
    var tag = document.activeElement ? document.activeElement.tagName : '';
    return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
}

function isPanelVisible() {
    return _panel && _panel.style.display !== 'none' && !_panel.classList.contains('sb-collapsed');
}

function handleKeyDown(e) {
    if (!_active || !isPanelVisible()) return;

    // Arrow keys: only when not typing in an input
    if (!isInputFocused()) {
        if (e.key === 'ArrowLeft') {
            e.preventDefault();
            var idx = state.getSelectedIndex();
            if (idx > 0) state.selectStep(idx - 1);
            return;
        }
        if (e.key === 'ArrowRight') {
            e.preventDefault();
            var idx = state.getSelectedIndex();
            if (idx < state.getSteps().length - 1) state.selectStep(idx + 1);
            return;
        }
        if (e.key === 'Delete') {
            e.preventDefault();
            state.removeStep(state.getSelectedIndex());
            return;
        }
    }

    // Ctrl shortcuts: work even when input focused
    if (e.ctrlKey || e.metaKey) {
        if (e.key === 'z' && !e.shiftKey) {
            e.preventDefault();
            state.undo();
            return;
        }
        if ((e.key === 'z' && e.shiftKey) || (e.key === 'Z' && e.shiftKey)) {
            e.preventDefault();
            state.redo();
            return;
        }
        if (e.key === 'c' && e.shiftKey && !isInputFocused()) {
            e.preventDefault();
            copyToClipboard(codegen.generateCopyAllText());
            return;
        }
        if (e.key === 'c' && !e.shiftKey && !isInputFocused()) {
            e.preventDefault();
            copyToClipboard(codegen.generateCopyStepText(state.getSelectedIndex()));
            return;
        }
        if (e.key === 'd' && !isInputFocused()) {
            e.preventDefault();
            state.duplicateStep(state.getSelectedIndex());
            return;
        }
        if (e.key === 'n' && !isInputFocused()) {
            e.preventDefault();
            state.addStep(state.getSelectedIndex());
            return;
        }
        if (e.key === 'f' && !isInputFocused()) {
            e.preventDefault();
            events.emit('capture-screenshot', { index: state.getSelectedIndex() });
            return;
        }
    }

    // Escape: close drawer, cancel pick, exit spectator
    if (e.key === 'Escape') {
        events.emit('escape-pressed');
    }
}

export function init(panel) {
    _panel = panel;
    _active = true;
    document.addEventListener('keydown', handleKeyDown);
}

export function destroy() {
    _active = false;
    _panel = null;
    document.removeEventListener('keydown', handleKeyDown);
}

export function setActive(active) {
    _active = active;
}
```

**Step 2: Commit**

```bash
git add packages/condoedge/utils/resources/js/tutorials/step-builder/shortcuts.js
git commit -m "feat(step-builder): add keyboard shortcuts module"
```

---

## Task 8: Create index.js entry point (wires everything together)

**Files:**
- Create: `resources/js/tutorials/step-builder/index.js`
- Modify: `resources/js/utils.js:92` — change import path

This is the entry point that preserves the IIFE auto-init pattern. It imports all modules and wires them together inside `_onReady`.

**Step 1: Write index.js (initial scaffold)**

For now, this wires up foundation modules only (state, events, styles, shortcuts). UI modules (sidebar, cards, timeline, etc.) will be added in subsequent tasks.

```javascript
// index.js — Step Builder v2 Entry Point
// IIFE auto-init pattern preserved for backward compatibility

import * as stateModule from './state';
import * as events from './events';
import { injectStyles } from './styles';
import * as shortcuts from './shortcuts';
import * as codegen from './codegen';
import { el, flashStatus, copyToClipboard } from './helpers';
import { ICONS, iconEl } from './icons';

export default (function() {
    'use strict';

    function _waitForEngine() {
        if (typeof TutorialEngine === 'undefined' || !TutorialEngine._onReady) {
            setTimeout(_waitForEngine, 50);
            return;
        }
        _initStepBuilder();
    }

    function _initStepBuilder() {
        TutorialEngine._onReady(function(ctx) {

            // --- Init foundation ---
            var styleEl = injectStyles();
            stateModule.init(ctx);

            // --- Build sidebar panel ---
            // TODO: Task 9+ will replace this with ui/sidebar.js
            var panel = el('div', {
                'data-sb': '',
                className: 'sb-sidebar',
                style: { position: 'fixed', top: '0', right: '0' },
            });

            // Placeholder header
            var header = el('div', { className: 'sb-header' }, [
                iconEl('menu', 16),
                el('span', { textContent: 'Step Builder v2', className: 'sb-header-title' }),
                (function() {
                    var devLabel = el('label', { className: 'sb-checkbox', style: { fontSize: '10px' } });
                    var devCb = el('input', { type: 'checkbox', checked: !!window._tutorialDevMode });
                    devCb.addEventListener('change', function() { window._tutorialDevMode = devCb.checked; });
                    devLabel.appendChild(devCb);
                    devLabel.appendChild(document.createTextNode('Dev'));
                    return devLabel;
                })(),
            ]);
            panel.appendChild(header);

            // Placeholder body
            var body = el('div', { className: 'sb-detail' }, [
                el('div', { textContent: 'Step Builder v2 — Foundation loaded. Cards coming next.', style: { color: '#7a7f8e', padding: '20px', textAlign: 'center' } }),
            ]);
            panel.appendChild(body);

            document.body.appendChild(panel);

            // --- Init shortcuts ---
            shortcuts.init(panel);

            // --- Sync step highlight when tutorial navigates ---
            ctx.overlay.addEventListener('tutorial-step-change', function(e) {
                if (e.detail && typeof e.detail.index === 'number') {
                    stateModule.selectStep(e.detail.index);
                }
            });

            // --- Cleanup when tutorial overlay is removed ---
            var origRemove = ctx.overlay.remove.bind(ctx.overlay);
            ctx.overlay.remove = function() {
                shortcuts.destroy();
                if (panel.parentNode) panel.parentNode.removeChild(panel);
                if (styleEl.parentNode) styleEl.parentNode.removeChild(styleEl);
                origRemove();
            };

        });
    }

    _waitForEngine();
})();
```

**Step 2: Update utils.js import path**

Change line 92 of `resources/js/utils.js`:

```javascript
// Before:
import initStepBuilder from './tutorials/tutorial-step-builder';

// After:
import initStepBuilder from './tutorials/step-builder/index';
```

**Step 3: Verify build compiles**

```bash
cd packages/condoedge/utils && npm run dev
```

Expected: Build succeeds, no errors. The old `tutorial-step-builder.js` still exists but is no longer imported.

**Step 4: Commit**

```bash
git add packages/condoedge/utils/resources/js/tutorials/step-builder/index.js
git add packages/condoedge/utils/resources/js/utils.js
git commit -m "feat(step-builder): add entry point with IIFE pattern, wire foundation modules"
```

---

## Task 9: Create sidebar layout module

**Files:**
- Create: `resources/js/tutorials/step-builder/ui/sidebar.js`
- Modify: `resources/js/tutorials/step-builder/index.js` — replace placeholder with sidebar

**Step 1: Write ui/sidebar.js**

The sidebar creates the 3-zone layout (step list, detail area, timeline) and handles resize + collapse.

```javascript
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

    var devCb = el('input', { type: 'checkbox', checked: !!window._tutorialDevMode });
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

    // --- Timeline zone ---
    var timelineZone = el('div', { className: 'sb-timeline' });
    panel.appendChild(timelineZone);

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
        timelineZone: timelineZone,
        historyDrawer: historyDrawer,
        isCollapsed: function() { return collapsed; },
        collapse: function() { collapseBtn.click(); },
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
```

**Step 2: Update index.js to use sidebar**

Replace the placeholder panel creation in `index.js` with:

```javascript
import { createSidebar } from './ui/sidebar';

// Inside _onReady callback, replace panel creation:
var sidebar = createSidebar();
var panel = sidebar.panel;
document.body.appendChild(panel);

// Use sidebar.detailZone, sidebar.stepListZone, sidebar.timelineZone
// for mounting cards, step list, and timeline respectively
```

**Step 3: Build and verify**

```bash
cd packages/condoedge/utils && npm run dev
```

**Step 4: Commit**

```bash
git add packages/condoedge/utils/resources/js/tutorials/step-builder/ui/sidebar.js
git add packages/condoedge/utils/resources/js/tutorials/step-builder/index.js
git commit -m "feat(step-builder): add sidebar layout with 3 zones, resize, collapse"
```

---

## Task 10: Create card system module

**Files:**
- Create: `resources/js/tutorials/step-builder/ui/cards.js`

This module manages the card toggle/expand system. Each property card registers itself here.

**Step 1: Write ui/cards.js**

```javascript
// ui/cards.js — Card system for Step Builder v2
// Cards register here. This module renders them based on step data and state.

import { el } from '../helpers';
import { iconEl } from '../icons';
import * as state from '../state';
import * as events from '../events';

var _cards = [];       // Registered card definitions
var _container = null; // DOM container (detailZone)

// Card definition: { name, icon, title, alwaysVisible, defaultVisible, hasData(step), render(container, step) }
export function registerCard(cardDef) {
    _cards.push(cardDef);
}

export function init(container) {
    _container = container;
}

export function renderCards() {
    if (!_container) return;
    _container.innerHTML = '';
    var step = state.getCurrentStep();
    if (!step) return;

    _cards.forEach(function(cardDef) {
        var hasData = cardDef.hasData ? cardDef.hasData(step) : false;
        var isExpanded = state.isCardExpanded(cardDef.name);

        // Determine visibility
        if (cardDef.alwaysVisible) {
            // Always shown (content card)
            renderCard(cardDef, step, true);
        } else if (hasData || cardDef.defaultVisible || isExpanded) {
            // Has data or is a default card or user expanded it
            renderCard(cardDef, step, hasData || isExpanded);
        }
        // Otherwise: hidden (can be added via "+")
    });

    // "+ Add Card" button for hidden cards
    var hiddenCards = _cards.filter(function(cd) {
        if (cd.alwaysVisible) return false;
        var hasData = cd.hasData ? cd.hasData(step) : false;
        return !hasData && !cd.defaultVisible && !state.isCardExpanded(cd.name);
    });

    if (hiddenCards.length) {
        var addContainer = el('div', { style: { padding: '8px 0' } });
        var addBtn = el('button', { className: 'sb-btn sb-btn-ghost', style: { width: '100%', justifyContent: 'center' } });
        addBtn.appendChild(iconEl('add', 14));
        addBtn.appendChild(document.createTextNode('Add Card'));
        addBtn.addEventListener('click', function() {
            // Show dropdown of hidden cards
            var menu = el('div', { style: {
                background: '#2a2a3d', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '8px',
                padding: '4px', marginTop: '4px', boxShadow: '0 4px 16px rgba(0,0,0,0.4)',
            }});
            hiddenCards.forEach(function(cd) {
                var item = el('div', {
                    style: { display: 'flex', alignItems: 'center', gap: '8px', padding: '6px 10px',
                             borderRadius: '6px', cursor: 'pointer', fontSize: '12px', color: '#b0b4c0' },
                });
                item.appendChild(iconEl(cd.icon, 14));
                item.appendChild(document.createTextNode(cd.title));
                item.addEventListener('mouseenter', function() { item.style.background = 'rgba(108,138,255,0.1)'; });
                item.addEventListener('mouseleave', function() { item.style.background = 'transparent'; });
                item.addEventListener('click', function() {
                    state.setCardExpanded(cd.name, true);
                    renderCards();
                });
                menu.appendChild(item);
            });
            addContainer.innerHTML = '';
            addContainer.appendChild(menu);
            // Close on click outside
            setTimeout(function() {
                document.addEventListener('click', function closeMenu(e) {
                    if (!addContainer.contains(e.target)) {
                        document.removeEventListener('click', closeMenu);
                        renderCards();
                    }
                });
            }, 0);
        });
        addContainer.appendChild(addBtn);
        _container.appendChild(addContainer);
    }
}

function renderCard(cardDef, step, expanded) {
    var card = el('div', { className: 'sb-card' + (expanded ? ' sb-card-active' : ' sb-card-off') });

    // Header
    var cardHeader = el('div', { className: 'sb-card-header' });
    cardHeader.appendChild(iconEl(cardDef.icon, 16));
    cardHeader.appendChild(el('span', { textContent: cardDef.title, className: 'sb-card-title' }));

    if (!cardDef.alwaysVisible) {
        var toggle = el('div', { className: 'sb-card-toggle' + (expanded ? ' sb-on' : '') });
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            var nowExpanded = !state.isCardExpanded(cardDef.name);
            state.setCardExpanded(cardDef.name, nowExpanded);
            if (!nowExpanded && cardDef.onDisable) {
                cardDef.onDisable(step);
            }
            renderCards();
        });
        cardHeader.appendChild(toggle);
    }

    cardHeader.addEventListener('click', function() {
        if (cardDef.alwaysVisible || expanded) {
            // Toggle collapse (visual only, data stays)
            var body = card.querySelector('.sb-card-body');
            if (body) body.style.display = body.style.display === 'none' ? '' : 'none';
        } else {
            state.setCardExpanded(cardDef.name, true);
            renderCards();
        }
    });

    card.appendChild(cardHeader);

    // Body
    if (expanded) {
        var cardBody = el('div', { className: 'sb-card-body' });
        cardDef.render(cardBody, step);
        card.appendChild(cardBody);
    }

    _container.appendChild(card);
}

// Listen for state changes to re-render
events.on('step-selected', renderCards);
events.on('state-restored', renderCards);
events.on('step-updated', renderCards);
events.on('step-added', renderCards);
events.on('step-removed', renderCards);
```

**Step 2: Commit**

```bash
git add packages/condoedge/utils/resources/js/tutorials/step-builder/ui/cards.js
git commit -m "feat(step-builder): add card system with toggle, auto-show, and add menu"
```

---

## Tasks 11-22: Individual Card Implementations

Each card follows the same pattern: register with `cards.registerCard()`, implement `hasData(step)` and `render(container, step)`.

> **Note for implementer:** Tasks 11-22 each create one card file following the exact same structure. They are listed here as outlines — the full code for each card should be derived from the design document §6.1-6.11 and the v1 implementations in `tutorial-step-builder.js`. The key difference is all mutations go through `state.updateStep()` instead of direct assignment.

### Task 11: Content Card (`cards/content-card.js`)
- 3 inputs: key (maps to step.html), FR (_textFr), EN (_textEn)
- Mini-preview div showing FR text
- Always visible, no toggle

### Task 12: Cursor Card (`cards/cursor-card.js`)
- From/To with Pick, waypoints, SVG path editor
- Duration, delay (NEW), ease, click, loop, image (NEW)
- Snap-to-element on drag release
- Preview with blue pulse in dev mode
- Reference buttons for highlight:N and hover:N

### Task 13: Highlight Card (`cards/highlight-card.js`)
- Element list with numbered selectors (editable text inputs)
- Mode select, padding input (NEW), borderRadius input (NEW)
- blockOutside checkbox
- Multi-pick mode
- Live highlight preview during pick

### Task 14: Hover Card (`cards/hover-card.js`)
- Selector list with hoverable parent walk-up
- Preview / Stop / Clear

### Task 15: Scroll Card (`cards/scroll-card.js`)
- scrollTo + scrollInside with Pick
- Mode (to/by), amount, duration
- Test / Clear

### Task 16: Options Card (`cards/options-card.js`)
- Option list with label, action type, params
- Add / Remove / Preview

### Task 17: Conditions Card (`cards/conditions-card.js`) — NEW
- showIf + hideIf with Pick and editable text input
- Clear buttons

### Task 18: Actions Card (`cards/actions-card.js`) — NEW
- silentClick, linkNext, redirect with Pick
- Dev mode badges

### Task 19: Advance Card (`cards/advance-card.js`)
- Radio: click / auto / afterAnimation
- Auto delay input
- Dev mode: paused bar with play button

### Task 20: Position Card (`cards/position-card.js`)
- Position, align selects
- positionTarget with Pick
- chatMode checkbox, chatMaxWidth input

### Task 21: Global Options Card (`cards/global-opts-card.js`)
- bubbleMaxWidth, bubbleMinWidth, bubbleFontSize
- cursorImage (NEW), avatarVideo (NEW)

### Task 22: Wire all cards in index.js
- Import all card files
- Call registerCard for each
- Call cards.init(sidebar.detailZone)

---

## Task 23: Create element picker module

**Files:**
- Create: `resources/js/tutorials/step-builder/ui/element-picker.js`
- Reference: `docs/plans/2026-03-26-step-builder-redesign.md` §7

Enhanced picker with: dimensions, breadcrumb, keyboard nav (↑↓←→), live highlight preview, multi-pick mode, manual selector edit.

---

## Task 24: Create step list module

**Files:**
- Create: `resources/js/tutorials/step-builder/ui/step-list.js`
- Reference: `docs/plans/2026-03-26-step-builder-redesign.md` §10

Collapsible vertical list in stepListZone. Drag & drop via HTML5 drag API. Synced with timeline and graph via events.

---

## Task 25: Create timeline module

**Files:**
- Create: `resources/js/tutorials/step-builder/ui/timeline.js`
- Reference: `docs/plans/2026-03-26-step-builder-redesign.md` §8

Horizontal scrollable strip, screenshot thumbnails, Ctrl+F capture, camera button, SVG icons, drag & drop, tab bar (Timeline | Graph).

---

## Task 26: Create graph view module

**Files:**
- Create: `resources/js/tutorials/step-builder/ui/graph-view.js`
- Reference: `docs/plans/2026-03-26-step-builder-redesign.md` §9

Mini-cards + diamond nodes, connections, branch colors, read-only + edit mode toggle. Branch management: double-click to rename, input in content card.

---

## Task 27: Create history drawer module

**Files:**
- Create: `resources/js/tutorials/step-builder/ui/history-drawer.js`
- Reference: `docs/plans/2026-03-26-step-builder-redesign.md` §11

Slide-in from left edge, action list, click to undo to point, current position highlight.

---

## Task 28: Spectator mode

**Files:**
- Modify: `resources/js/tutorials/step-builder/index.js`

Spectator: hide sidebar, set `_tutorialDevMode = false`, restart from step 0, floating "Quitter" button, Escape to exit, restore sidebar + dev mode on exit.

---

## Task 29: Engine changes — dev mode behavior

**Files:**
- Modify: `resources/js/tutorials/tutorial-engine.js`
- Reference: `docs/plans/2026-03-26-step-builder-redesign.md` §14

Changes:
1. `createClickPulse(x, y, color)` — add color parameter, default `#ff4444`, use `#4a6cf7` in dev mode
2. `createRing(x, y, bgOpacity, scale, duration, delay, color)` — add color param
3. In `animateCursor` click handler: check `_tutorialDevMode`, use blue pulse, skip `.click()`
4. In `animateCursorWaypoints` click action: same dev mode check
5. In `showStep` silentClick handler: if dev mode, DON'T click, DON'T skip, show step normally with badge + highlight
6. In `showStep` autoNext handler: if dev mode, always `pauseAutoNext()`, show play button
7. In `showStep` afterAnimation handler: if dev mode, show Next button, don't auto-advance
8. In `showStep` linkNext: if dev mode, don't call `setupLinkNext`, show badge instead

---

## Task 30: Update tutorial-creator skill

**Files:**
- Modify: skill file for `/tutorial-creator`

Update to handle new properties: `_branch`, `hideIf`, `linkNext`, `redirect` (step-level), `cursor.delay`, `cursor.image`, `highlight.padding/borderRadius`.

---

## Task 31: Remove old step builder file

**Files:**
- Delete: `resources/js/tutorials/tutorial-step-builder.js`

Only after all previous tasks are complete and tested.

```bash
git rm packages/condoedge/utils/resources/js/tutorials/tutorial-step-builder.js
git commit -m "chore(step-builder): remove v1 step builder (replaced by step-builder/)"
```

---

## Task 32: Build, test, and verify backward compatibility

**Step 1: Build**

```bash
cd /c/Repository/Decizif/SISC/SISC && npm run dev
```

**Step 2: Verify**

- Load a page with an existing tutorial (e.g., DashboardView)
- Confirm Step Builder v2 sidebar appears on the right
- Confirm all existing step properties display correctly in cards
- Confirm live preview updates when editing
- Confirm Copy All generates correct JS code
- Confirm Spectator mode works (real clicks, autoNext)
- Confirm Dev mode works (no real clicks, autoNext paused)
- Confirm undo/redo works
- Confirm keyboard shortcuts work
- Confirm drag & drop reorder works in all 3 views
- Confirm `intro-*.js` files continue loading without changes

**Step 3: Final commit**

```bash
git commit -m "feat(step-builder): complete v2 redesign — modular, Figma-inspired, full property coverage"
```
