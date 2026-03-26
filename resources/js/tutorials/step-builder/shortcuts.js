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
