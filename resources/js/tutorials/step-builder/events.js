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
