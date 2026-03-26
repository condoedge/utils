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
