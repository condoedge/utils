# Step Builder v2 — Complete Redesign Plan

**Date:** 2026-03-26
**Status:** Design validated, ready for implementation
**Scope:** Complete rewrite of `tutorial-step-builder.js` into modular architecture

---

## 1. Architecture

### File Structure

```
tutorials/
├── tutorial-engine.js                (UNCHANGED — only dev mode behavior changes)
├── step-builder/
│   ├── index.js                      (entry point, IIFE auto-init, waitForEngine)
│   ├── state.js                      (central store, undo/redo, mutations)
│   ├── events.js                     (event bus: on/emit/off)
│   ├── styles.js                     (CSS injection via <style> tag, [data-sb] scoping)
│   ├── codegen.js                    (generateStepCode, generateFullCode, escStr)
│   ├── shortcuts.js                  (keyboard shortcuts)
│   ├── ui/
│   │   ├── sidebar.js                (layout, header, collapse, resize)
│   │   ├── timeline.js               (horizontal timeline + screenshots + drag & drop)
│   │   ├── graph-view.js             (flow/graph view for branches)
│   │   ├── step-list.js              (vertical list, collapsible, sync with timeline/graph)
│   │   ├── cards.js                  (card system: toggle on/off, "+", render)
│   │   ├── element-picker.js         (crosshair + breadcrumb + dimensions + keyboard nav)
│   │   └── history-drawer.js         (undo/redo slide-in drawer from left edge)
│   └── cards/
│       ├── content-card.js           (translation key + FR input + EN input + preview)
│       ├── cursor-card.js            (from/to, waypoints, SVG path editor, preview)
│       ├── highlight-card.js         (groups, mode, pick, blockOutside, padding, borderRadius)
│       ├── hover-card.js             (hover selectors, preview, stop)
│       ├── scroll-card.js            (scrollTo, scrollInside)
│       ├── options-card.js           (step options: label, action, redirect, goToStep, done)
│       ├── conditions-card.js        (showIf, hideIf — each with Pick button)
│       ├── actions-card.js           (silentClick, linkNext, redirect step-level)
│       ├── advance-card.js           (click / auto / afterAnimation)
│       ├── position-card.js          (position, align, positionTarget, chatMode, chatMaxWidth)
│       └── global-opts-card.js       (bubbleMaxWidth, bubbleMinWidth, bubbleFontSize, avatarVideo, cursorImage)
```

### Entry Point Pattern (MUST match current behavior)

```javascript
// index.js
export default (function() {
    function _waitForEngine() {
        if (typeof TutorialEngine === 'undefined' || !TutorialEngine._onReady) {
            setTimeout(_waitForEngine, 50);
            return;
        }
        _initStepBuilder();
    }
    function _initStepBuilder() {
        TutorialEngine._onReady(function(ctx) {
            // Import and init all modules with ctx
            // ...
        });
    }
    _waitForEngine();
})();
```

### Import from utils.js (UNCHANGED)

```javascript
// utils.js — no changes needed
import initStepBuilder from './tutorials/step-builder/index';
```

---

## 2. State Management (state.js)

### Single Source of Truth

**`ctx.steps` IS the source of truth.** The builder works directly on `ctx.steps` — no separate `peSteps` array, no `syncToLive()`.

```javascript
// state.js
var state = {
    ctx: null,                    // TutorialEngine context (set once at init)
    selectedIndex: 0,             // Currently selected step
    originalSteps: [],            // Deep clone at load time (read-only, for change detection)
    undoStack: [],                // Array of full ctx.steps snapshots
    redoStack: [],                // Array of full ctx.steps snapshots
    maxUndoSize: 50,              // Limit undo stack size
    screenshots: {},              // { [stepIndex]: dataURL }
    expandedCards: {},             // { cursor: true, highlight: false, ... }
    viewMode: 'timeline',         // 'timeline' | 'graph' | 'list'
};
```

### Mutations

All modifications go through mutation functions that:
1. Push a snapshot to `undoStack` (deep clone of `ctx.steps`, capped at 50)
2. Clear `redoStack`
3. Modify `ctx.steps` directly
4. Emit event via event bus
5. Debounce `ctx.showStep(selectedIndex)` at 300ms

```javascript
function pushUndo() {
    state.undoStack.push(JSON.parse(JSON.stringify(state.ctx.steps)));
    if (state.undoStack.length > state.maxUndoSize) state.undoStack.shift();
    state.redoStack = [];
}

function addStep(afterIndex) {
    pushUndo();
    var newStep = { html: '', overlay: true, position: 'left', align: 'center' };
    state.ctx.steps.splice(afterIndex + 1, 0, newStep);
    state.selectedIndex = afterIndex + 1;
    events.emit('step-added', { index: afterIndex + 1 });
    scheduleRefresh();
}

function removeStep(index) {
    if (state.ctx.steps.length <= 1) return;
    pushUndo();
    state.ctx.steps.splice(index, 1);
    state.selectedIndex = Math.min(index, state.ctx.steps.length - 1);
    events.emit('step-removed', { index: index });
    scheduleRefresh();
}

function updateStep(index, key, value) {
    pushUndo();
    state.ctx.steps[index][key] = value;
    events.emit('step-updated', { index: index, key: key });
    scheduleRefresh();
}

function moveStep(fromIndex, toIndex) {
    pushUndo();
    var step = state.ctx.steps.splice(fromIndex, 1)[0];
    state.ctx.steps.splice(toIndex, 0, step);
    state.selectedIndex = toIndex;
    events.emit('step-moved', { from: fromIndex, to: toIndex });
    scheduleRefresh();
}

function selectStep(index) {
    state.selectedIndex = Math.max(0, Math.min(index, state.ctx.steps.length - 1));
    events.emit('step-selected', { index: state.selectedIndex });
    state.ctx.showStep(state.selectedIndex); // Immediate, no debounce
}

function undo() {
    if (!state.undoStack.length) return;
    state.redoStack.push(JSON.parse(JSON.stringify(state.ctx.steps)));
    var snapshot = state.undoStack.pop();
    state.ctx.steps.length = 0;
    snapshot.forEach(function(s) { state.ctx.steps.push(s); });
    state.selectedIndex = Math.min(state.selectedIndex, state.ctx.steps.length - 1);
    events.emit('state-restored');
    state.ctx.showStep(state.selectedIndex);
}

function redo() {
    if (!state.redoStack.length) return;
    state.undoStack.push(JSON.parse(JSON.stringify(state.ctx.steps)));
    var snapshot = state.redoStack.pop();
    state.ctx.steps.length = 0;
    snapshot.forEach(function(s) { state.ctx.steps.push(s); });
    state.selectedIndex = Math.min(state.selectedIndex, state.ctx.steps.length - 1);
    events.emit('state-restored');
    state.ctx.showStep(state.selectedIndex);
}

// Debounced refresh for live preview (300ms)
var _refreshTimer = null;
function scheduleRefresh() {
    if (_refreshTimer) clearTimeout(_refreshTimer);
    _refreshTimer = setTimeout(function() {
        state.ctx.showStep(state.selectedIndex);
        _refreshTimer = null;
    }, 300);
}
```

### Change Detection

```javascript
function getStepChanges(index) {
    var orig = state.originalSteps[index];
    var curr = state.ctx.steps[index];
    // Compare, ignoring internal props (_pe, _selectors, _mode, _groups)
    // Return array of changed property names
}
```

---

## 3. Event Bus (events.js)

```javascript
var listeners = {};

function on(event, callback) { ... }
function off(event, callback) { ... }
function emit(event, data) { ... }

// Events:
// 'step-added'      { index }
// 'step-removed'    { index }
// 'step-updated'    { index, key }
// 'step-moved'      { from, to }
// 'step-selected'   { index }
// 'state-restored'  {}
// 'view-changed'    { mode }
// 'card-toggled'    { card, expanded }
```

---

## 4. Layout (ui/sidebar.js)

### Structure

```
┌─────────────────────────────────────┐
│ HEADER (48px)                       │
│ [≡] Step Builder    [Dev ☐] [—]     │
├─────────────────────────────────────┤
│ STEP LIST (collapsible)             │
│ Compact vertical list of steps      │
│ with drag & drop reorder            │
├─────────────────────────────────────┤
│ STEP DETAIL (scrollable)            │
│                                     │
│ ┌─ Content Card ──────────────────┐ │
│ │ key: [tutorial.dash.welcome   ] │ │
│ │ FR:  [Bienvenue sur le tableau] │ │
│ │ EN:  [Welcome to the dashboard] │ │
│ └─────────────────────────────────┘ │
│                                     │
│ ┌─ Cursor Card ──── [ON] ────────┐ │
│ │ from: .sidebar        [Pick]   │ │
│ │ to:   .search-bar     [Pick]   │ │
│ │ dur: 1.5  ease: power2.inOut   │ │
│ │ [▶ Preview] [Show Path] [Clear]│ │
│ └─────────────────────────────────┘ │
│                                     │
│ ┌─ Highlight Card ── [ON] ───────┐ │
│ │ 1. .vlNavbar                   │ │
│ │ mode: together  padding: 8     │ │
│ │ [+ Pick] [▶ Preview] [Clear]   │ │
│ └─────────────────────────────────┘ │
│                                     │
│ ○ Hover      (off — click to add)  │
│ ○ Scroll     (off — click to add)  │
│ ○ Conditions (off — click to add)  │
│ ○ Actions    (off — click to add)  │
│ ○ Advance    (off — click to add)  │
│ ○ Position   (off — click to add)  │
│ ○ Options    (off — click to add)  │
│                                     │
│ [+ Add Card ▼]                      │
│                                     │
├─────────────────────────────────────┤
│ TIMELINE (180px, bottom)            │
│ ┌───┐ ┌───┐ ┌───┐ ┌───┐ ┌───┐     │
│ │ 0 │ │ 1 │ │ 2 │ │ 3 │ │ 4 │ ←→  │
│ │ 📷│ │ 📷│ │ 📷│ │ 📷│ │ 📷│     │
│ └───┘ └───┘ └───┘ └───┘ └───┘     │
│ [Timeline] [Graph] [Copy ▼]        │
└─────────────────────────────────────┘
```

### Specifications

- **Position:** Fixed right sidebar, full viewport height
- **Width:** 420px default, resizable from left edge
- **Background:** `#1e1e2e` (deep navy)
- **Collapsed state:** 48px wide, only expand button visible, nothing else
- **Z-index:** 99997+ (same range as current)
- **The page compresses** to `calc(100% - 420px)` when sidebar is open

### Header (48px)

- Menu icon (SVG, not emoji)
- "Step Builder" title
- Dev mode toggle checkbox
- Collapse button

### Sidebar Sections

Three independently scrollable zones:
1. **Step List** — collapsible, compact vertical list
2. **Step Detail** — scrollable, cards area
3. **Timeline** — fixed height 180px at bottom

---

## 5. Card System (ui/cards.js)

### Card States

Each card has 3 states:

1. **Hidden** — not shown at all (added via "+" button)
2. **Collapsed/Off** — single line: icon + name + toggle switch (OFF)
3. **Expanded/On** — full card with all controls, toggle switch (ON)

### Default Cards for New Step

- **Content** — always visible, cannot be toggled off
- **Cursor** — visible, initially collapsed
- **Highlight** — visible, initially collapsed
- All others — hidden, added via "+" button

### Auto-Show Logic

When loading an existing step, cards with data auto-expand:
- Step has `.cursor` → Cursor card expanded
- Step has `.highlight` → Highlight card expanded
- Step has `.hover` → Hover card visible + expanded
- Step has `.showIf` or `.hideIf` → Conditions card visible + expanded
- etc.

### Card Toggle Behavior

- Toggle ON → card expands, property is initialized with defaults
- Toggle OFF → card collapses, property is set to `null`/`undefined` on the step
- This ensures no orphan properties on steps

---

## 6. Cards Detail

### 6.1 Content Card (content-card.js)

Always visible, cannot be toggled off.

| Field | Type | Description |
|-------|------|-------------|
| key | text input | Translation key (e.g., `tutorial.dashboard.welcome`) |
| FR | text input | French text (displayed in live preview) |
| EN | text input | English text |
| Preview | read-only div | Renders the FR text as it would appear in the bubble |

- The `key` maps to `step.html`
- FR/EN are stored as `step._textFr` and `step._textEn` (internal, not exported)
- Live preview shows FR text in a mini-bubble below the inputs
- When key changes, if a translation exists in the DOM, auto-populate FR/EN

### 6.2 Cursor Card (cursor-card.js)

| Field | Type | Description |
|-------|------|-------------|
| from | selector + Pick button | Starting element |
| fromAnchor | badge (x, y) | Anchor point on from element (0-1) |
| to | selector + Pick button | Ending element |
| toAnchor | badge (x, y) | Anchor point on to element |
| waypoints | list | Intermediate waypoints with action/pause |
| duration | number | Animation speed (default 1.5s) |
| delay | number | Wait before starting (seconds) — **NEW** |
| ease | select | Easing function |
| click | checkbox | Auto-click at destination |
| loop | checkbox | Repeat animation |
| image | text input | Custom cursor image URL — **NEW** |

**Path Editor (SVG overlay):**
- Yellow dashed Bézier curves
- Draggable control points (orange cp1, pink cp2)
- Draggable waypoint dots (green start, red end, blue intermediate)
- **Snap to element:** when dragging a point and releasing over a DOM element, auto-attach to that element's selector instead of screen coords
- **+ Add Point** button picks a new waypoint
- **Show Path / Hide Path** toggles SVG overlay

**Waypoint properties:**
- Target selector or screen:X,Y
- Action: path | click | hover
- Pause duration (seconds)
- SVG path data (auto-computed from control points)

**Preview button behavior (dev mode):**
- Cursor animation plays fully
- Click action shows blue pulse ring (simulated, no real click)
- No `.click()` executed on any element

**Reference buttons:**
- Quick-link buttons for `highlight:N` and `hover:N` references for from/to fields

### 6.3 Highlight Card (highlight-card.js)

| Field | Type | Description |
|-------|------|-------------|
| elements | list | CSS selectors with numbered list |
| mode | select | together / separate / custom groups |
| padding | number | Padding around elements (default 8) — **NEW in UI** |
| borderRadius | number | Border radius (default 8) — **NEW in UI** |
| blockOutside | checkbox | Block interaction outside highlights |
| custom groups | number inputs per element | Group assignment (when mode=custom) |

**Pick behavior:**
- Live highlight preview during picking (not just blue outline)
- After picking, selector is editable as text input — **NEW**
- Can pick multiple elements without re-clicking Pick button — **NEW**

**Preview / Clear buttons**

### 6.4 Hover Card (hover-card.js)

| Field | Type | Description |
|-------|------|-------------|
| selectors | list | Elements to force :hover state |
| Pick | button | Walks up to nearest hoverable parent |

- Preview: applies force hover styles
- Stop: removes force hover
- Clear: removes all hover selectors

### 6.5 Scroll Card (scroll-card.js)

| Field | Type | Description |
|-------|------|-------------|
| scrollTo | selector + Pick | Element to scroll into view |
| scrollInside.selector | selector + Pick | Container to scroll within |
| scrollInside.mode | select | "to" (absolute) or "by" (relative) |
| scrollInside.amount | number | Scroll amount (pixels) |
| scrollInside.duration | number | Animation duration (seconds) |

- Test button: executes scroll to verify
- Clear button

### 6.6 Options Card (options-card.js)

| Field | Type | Description |
|-------|------|-------------|
| options[] | list | Array of option buttons |
| option.label | text | Translation key for button label |
| option.action | select | next / goToStep / redirect / done |
| option.goToStep | number | Target step index (when action=goToStep) |
| option.redirect | text | URL path (when action=redirect) |
| option.startTutorial | checkbox | Auto-start tutorial after redirect |

- "+ Add Option" button
- Remove button per option
- Preview button: renders options in the live bubble
- When options exist, advance mode is automatically disabled

### 6.7 Conditions Card (conditions-card.js) — **NEW**

| Field | Type | Description |
|-------|------|-------------|
| showIf | selector + Pick | Skip step if element NOT in DOM |
| hideIf | selector + Pick | Skip step if element IS in DOM |

- Each has a Pick button + clear button
- Selector is editable as text input

### 6.8 Actions Card (actions-card.js) — **NEW**

| Field | Type | Description |
|-------|------|-------------|
| silentClick | selector + Pick | Click element and advance (no UI shown) |
| linkNext | selector + Pick | Link page button to tutorial Next |
| redirect | text input | Navigate to URL on Next click |

**silentClick in dev mode:**
- Step displays normally (not silently skipped)
- Shows a highlight on the target element
- Badge "Silent Click → .selector" floats near the element
- Next button advances manually
- No `.click()` executed

**linkNext in dev mode:**
- Step displays normally
- Next button is visible (not hidden)
- No event listener attached to the page button
- Badge shows which element would be linked

### 6.9 Advance Card (advance-card.js)

| Field | Type | Description |
|-------|------|-------------|
| mode | radio buttons | click / auto / afterAnimation |
| autoNextDelay | number | Delay in seconds (when mode=auto) |

**Auto mode in dev mode:**
- Progress bar appears but starts paused
- Play button to test the timing
- Bar animates but does NOT trigger auto-advance
- Next button remains visible for manual advance

**afterAnimation in dev mode:**
- Does not trigger auto-advance
- Next button remains visible

### 6.10 Position Card (position-card.js)

| Field | Type | Description |
|-------|------|-------------|
| position | select | left / right / top / bottom |
| align | select | left / center / right |
| positionTarget | selector + Pick | Anchor bubble near DOM element |
| chatMode | checkbox | Chat-style presentation |
| chatMaxWidth | text input | Max width in chat mode |

### 6.11 Global Options Card (global-opts-card.js)

Lives outside the per-step cards, in the sidebar header area or a dedicated section.

| Field | Type | Description |
|-------|------|-------------|
| bubbleMaxWidth | text input | e.g., `clamp(260px, 85vw, 550px)` |
| bubbleMinWidth | text input | |
| bubbleFontSize | text input | |
| cursorImage | text input | Custom cursor image path — **NEW** |
| avatarVideo | text input | Video avatar URL — **NEW** |

---

## 7. Element Picker (ui/element-picker.js)

### Enhanced Features

| Feature | Description |
|---------|-------------|
| **Outline** | Blue outline around hovered element |
| **Live highlight** | Actual highlight overlay preview (not just outline) during pick |
| **Dimensions** | Shows `width × height` next to the selector label |
| **Breadcrumb** | DOM hierarchy: `body > div.container > .card > button` |
| **Keyboard nav** | ↑ = parent element, ↓ = first child, ← → = siblings |
| **Selector label** | Shows CSS selector above element |
| **Manual edit** | After picking, the selector is an editable text input |
| **Multi-pick** | For highlight card: keep picking until Escape, each pick adds to list |
| **Cancel** | Escape key cancels pick mode |
| **Touch support** | Tap to pick on touch devices |

### Anchor Point

When picking for cursor from/to:
- Calculates `(clickX - rect.left) / rect.width` for anchor
- Displays as badge `(0.3, 0.7)` next to the selector

---

## 8. Timeline (ui/timeline.js)

### Layout

- Fixed 180px height at bottom of sidebar
- Horizontal scrollable strip of step thumbnails
- Below thumbnails: tab buttons [Timeline] [Graph] + action buttons [Copy ▼]

### Step Thumbnails

- Each step shows:
  - Step number
  - Screenshot (manual capture via Ctrl+F or button)
  - SVG icons for active properties (cursor, highlight, options, autoNext)
  - Blue border if selected
  - Subtle colored dot if step has been modified since load
- **Drag & drop** to reorder steps
- **Click** to select step

### Screenshot Capture

- **Ctrl+F** keyboard shortcut captures screenshot of current page state for current step
- **Camera button** on each thumbnail for manual capture
- Screenshots stored in `state.screenshots[index]` as data URLs
- Placeholder thumbnail (gradient + step number) when no screenshot

### SVG Icons (not emojis)

```
Cursor:     crosshair icon
Highlight:  spotlight/flashlight icon
Options:    list/menu icon
AutoNext:   fast-forward icon
Hover:      pointer/hand icon
Scroll:     scroll/arrow-down icon
SilentClick: ghost/invisible click icon
LinkNext:   chain-link icon
```

---

## 9. Graph View (ui/graph-view.js)

### Layout

Replaces timeline area when "Graph" tab is selected.

### Node Types

- **Normal steps:** Mini-cards with number + property icons + branch color
- **Condition nodes:** Diamond shapes for steps with `showIf`/`hideIf`
- **Branch labels:** Color-coded labels above grouped steps

### Connections

- Lines connecting sequential steps
- Branching lines for `options` with `goToStep`
- Dotted lines for `redirect` connections

### Interaction Modes

- **Read-only (default):** Pan and zoom, click to select step, visual only
- **Edit mode (toggle button):** Can create/delete branches, drag connections

### Branch Management

- Double-click branch separator to rename
- In edit mode: drag a step to a different branch
- Branch colors auto-assigned from a palette

---

## 10. Step List (ui/step-list.js)

### Layout

Collapsible section at top of sidebar, above the cards.

### Features

- Compact vertical list: one row per step
- Each row: step number + first words of text + property icons + modified indicator
- **Drag & drop** to reorder (synced with timeline and graph)
- Click to select step
- **Synchronized** with timeline and graph view — any reorder in one view updates all

---

## 11. History Drawer (ui/history-drawer.js)

### Layout

Slides in from the left edge of the sidebar, pushes the card content to the right.

### Content

- List of actions in chronological order (newest first)
- Each entry: icon + description + timestamp
- Examples:
  - `✎ Step 3: changed html`
  - `+ Added step 4`
  - `↕ Moved step 2 → 5`
  - `✕ Removed step 6`
- Click an entry to undo to that point
- Current position highlighted

### Trigger

- Ctrl+Z = undo
- Ctrl+Shift+Z = redo
- History icon button in header opens the drawer

---

## 12. Keyboard Shortcuts (shortcuts.js)

| Shortcut | Action |
|----------|--------|
| ← | Previous step |
| → | Next step |
| Ctrl+Z | Undo |
| Ctrl+Shift+Z | Redo |
| Ctrl+C | Copy current step code |
| Ctrl+Shift+C | Copy all steps code |
| Delete | Delete current step (undo-able, no confirmation) |
| Ctrl+D | Duplicate current step |
| Ctrl+N | New step after current |
| Ctrl+F | Capture screenshot for current step thumbnail |
| Escape | Cancel pick mode / exit spectator / close drawer |

**Note:** Shortcuts only active when Step Builder panel has focus or is visible. Do not interfere with page shortcuts when typing in inputs.

---

## 13. Spectator Mode

### Behavior

1. Sidebar collapses to nothing (hidden)
2. SVG path overlay hidden
3. Tutorial restarts from step 0 with normal behavior:
   - All clicks execute normally
   - autoNext runs normally
   - silentClick executes normally
   - linkNext attaches normally
4. Floating button "✕ Quitter" top-right
5. Escape key exits spectator

### Exit

- Returns to builder view
- Sidebar re-opens
- Navigates to previously selected step

---

## 14. Dev Mode Behavior (tutorial-engine.js changes)

When `window._tutorialDevMode === true`:

| Feature | Production behavior | Dev mode behavior |
|---------|-------------------|------------------|
| **Cursor click** | `.click()` on target element | Blue pulse ring (simulated), no `.click()` |
| **autoNext** | Timer runs, auto-advances | Progress bar paused, Play button to test timing, no auto-advance |
| **afterAnimation** | Auto-advances after cursor | Next button stays visible, no auto-advance |
| **silentClick** | Clicks element, skips to next step | Step shows normally with highlight on target + "Silent Click" badge, manual Next |
| **linkNext** | Attaches listener to page button | Badge shows linked element, no listener attached, Next button visible |
| **Typewrite speed** | `opts.typewriteSpeed` (25ms) | 0 (instant) — already implemented |

### Click Pulse Colors

- **Production:** Red rings (`#ff4444`)
- **Dev mode preview:** Blue rings (`#4a6cf7`)

### Implementation

Modify `tutorial-engine.js`:

```javascript
// In animateCursor — click handler:
if (cfg.click) {
    var pulseColor = window._tutorialDevMode ? '#4a6cf7' : '#ff4444';
    createClickPulse(pulseX, pulseY, pulseColor); // Add color param
    if (!window._tutorialDevMode) {
        var targetEl = document.querySelector(resolveRef(cfg.to, step));
        if (targetEl && typeof targetEl.click === 'function') targetEl.click();
    }
}

// In showStep — silentClick handler:
if (step.silentClick && !_isAutoAdvancing) {
    if (window._tutorialDevMode) {
        // Show step normally, display badge + highlight
        // Do NOT click, do NOT auto-advance
        // Continue to render bubble/text normally
    } else {
        // Production: click and skip as before
    }
}

// In showStep — autoNext handler:
if (step.autoNext && !isLast) {
    if (window._tutorialDevMode) {
        pauseAutoNext(); // Always pause in dev mode
    }
}

// In showStep — afterAnimation handler:
if (step.afterAnimation && !isLast) {
    if (window._tutorialDevMode) {
        // Show Next button, don't auto-advance
        actionBtn.style.display = '';
    }
}
```

---

## 15. Code Generation (codegen.js)

### generateStepCode(step)

Exports ALL properties, filters internal `_` prefixed props except `_branch`.

**Properties exported (complete list):**

```javascript
// Always check and export if defined:
html, overlay, clearOverlay, showBack,
position, align, chatMode, chatMaxWidth, positionTarget,
showIf, hideIf,                    // NEW
silentClick, linkNext, redirect,   // NEW
_branch,                           // NEW (as _branch property)
cursor: {
    from, fromAnchor, to, toAnchor,
    svgPath, waypoints,
    duration, delay,               // NEW: delay
    ease, click, loop,
    image,                         // NEW
},
highlight: {
    groups, padding, borderRadius,
    blockOutside,
},
hover,
scrollTo,
scrollInside: { selector, to, by, duration },
options: [{ label, done, redirect, startTutorial, goToStep }],
autoNext, afterAnimation,
```

**Internal properties stripped from export:**
`_pe, _selectors, _mode, _groups, _textFr, _textEn, advance, autoNextDelay`

### generateFullCode()

Same format as current:
```javascript
$(document).ready(function(){
    var steps = [ ... ];
    TutorialEngine.start(steps, { ... });
});
```

### Copy Buttons

| Button | Output |
|--------|--------|
| Copy All | `/tutorial-creator\n\nPage: path\nChanges: ...\n\n<full code>` |
| Copy Step | `/tutorial-creator\n\nPage: path\nStep N\n\n<step code>` |
| Copy Changed | `/tutorial-creator\n\nPage: path\nChanged steps: ...\n\n<changed steps code>` |

Steps with modifications since load are visually marked (colored dot in timeline/list).

---

## 16. Visual Design

### Color Palette

| Element | Color |
|---------|-------|
| Background | `#1e1e2e` |
| Card surface | `#2a2a3d` |
| Card border | `rgba(255,255,255,0.08)` |
| Card active shadow | `0 2px 8px rgba(0,0,0,0.3)` |
| Accent / primary | `#6c8aff` |
| Success / green | `#2ecc71` |
| Danger / red | `#e74c3c` |
| Warning / yellow | `#f1c40f` |
| Text primary | `#e0e4ec` |
| Text secondary | `#7a7f8e` |
| Input background | `rgba(255,255,255,0.06)` |
| Input border | `rgba(255,255,255,0.1)` |
| Input focus border | `#6c8aff` |
| Input focus glow | `0 0 0 2px rgba(108,138,255,0.15)` |
| Scrollbar thumb | `rgba(255,255,255,0.1)` |

### Typography

- Font: `system-ui, -apple-system, sans-serif`
- Body: 13px
- Labels: 11px
- Headers: 14px, 700 weight, uppercase, `#6c8aff`
- Code/selectors: `"Fira Code", "Cascadia Code", monospace`, 12px

### Radius & Spacing

- Border radius: 8px everywhere (cards, inputs, buttons)
- Card padding: 12px
- Gap between cards: 8px
- Section padding: 14px 16px

### Transitions

- All toggles/expands: 200ms ease
- Card expand/collapse: 200ms ease (height animation)
- Button hover: `filter: brightness(1.15)`, `transform: translateY(-1px)`, 150ms

### SVG Icons

All icons are inline SVG, 16×16px, stroke-based, 1.5px stroke width, `currentColor`.

No emojis anywhere in the UI.

---

## 17. Cursor Path Editor — Snap to Element

### Behavior

When dragging a point (From, To, or waypoint) and releasing:

1. Get element at drop position via `document.elementFromPoint(x, y)`
2. If element found and is not the overlay/builder panel:
   - Compute `bestSelector(element)`
   - Compute anchor `{ x: (dropX - rect.left) / rect.width, y: (dropY - rect.top) / rect.height }`
   - Update cursor config with selector + anchor (instead of screen coords)
3. If no element found (dropped on overlay): keep as `screen:X,Y`

This replaces the current behavior where drag always produces screen coords.

---

## 18. Tutorial-Creator Skill Updates

The `/tutorial-creator` skill must be updated to handle:

1. **`_branch` property** — recognize and preserve branch assignments
2. **`hideIf` property** — new conditional
3. **`linkNext` property** — new action
4. **`redirect` (step-level)** — new action (different from option.redirect)
5. **`cursor.delay`** — new cursor property
6. **`cursor.image`** — new cursor property
7. **`highlight.padding` and `highlight.borderRadius`** — now configurable per step
8. **FR/EN text inputs** — when pasted output includes `_textFr`/`_textEn`, use them for translation file generation

---

## 19. Migration from v1

### Backward Compatibility

- The new `index.js` exports the same IIFE pattern — no changes to `utils.js` or any consuming code
- Existing `intro-*.js` files continue to work unchanged
- `HasIntroAnimation.php` continues to work unchanged
- `window.TutorialEngine` and `window.initStepBuilder` remain the same globals

### Data Compatibility

- All existing step properties are supported
- New properties (`hideIf`, `linkNext`, etc.) are additive — old tutorials without them work fine
- `_branch` is a new property — old tutorials without it display as single linear flow

---

## 20. Implementation Order

### Phase 1: Foundation (files: index.js, state.js, events.js, styles.js)
- Entry point with IIFE pattern
- State management with undo/redo
- Event bus
- CSS injection

### Phase 2: Core UI (files: sidebar.js, cards.js, element-picker.js)
- Sidebar layout (fixed right, header, collapse)
- Card system (toggle, expand/collapse, auto-show)
- Enhanced element picker

### Phase 3: Existing Cards (files: all cards/ except new ones)
- content-card.js (with FR/EN/key)
- cursor-card.js (with path editor, snap-to-element, delay, image)
- highlight-card.js (with padding/borderRadius inputs)
- hover-card.js
- scroll-card.js
- options-card.js
- advance-card.js
- position-card.js
- global-opts-card.js

### Phase 4: New Cards + Features
- conditions-card.js (showIf, hideIf)
- actions-card.js (silentClick, linkNext, redirect)
- Code generation (codegen.js)
- Keyboard shortcuts (shortcuts.js)

### Phase 5: Navigation Views
- timeline.js (screenshots, drag & drop, SVG icons)
- step-list.js (vertical list, sync)
- graph-view.js (branch visualization, edit mode)
- history-drawer.js (undo/redo visual list)

### Phase 6: Engine Changes
- Dev mode: blue click pulse
- Dev mode: silentClick display as step
- Dev mode: autoNext always paused with play button
- Dev mode: afterAnimation no auto-advance
- Dev mode: linkNext disabled

### Phase 7: Skill Updates
- Update `/tutorial-creator` skill for new properties
- Update code generation format

### Phase 8: Testing & Polish
- Test all card interactions
- Test undo/redo across all operations
- Test drag & drop sync between views
- Test spectator mode
- Test keyboard shortcuts
- Test element picker enhancements
- Verify backward compatibility with existing tutorials
