/**
 * TutorialEngine — Shared tutorial functionality.
 * Auto-loaded by HasIntroAnimation.php before any intro-*.js file.
 *
 * Usage in tutorial files:
 *   var steps = [ { html: "...", cursor: {...}, highlight: {...} } ];
 *   TutorialEngine.start(steps, { avatar: '/images/Benoit helper.png' });
 */
export default function(gsap) {
    'use strict';

    // === DEPENDENCY GUARD ===
    if (typeof gsap === 'undefined') {
        console.error('TutorialEngine: gsap is required but not loaded.');
        return {
            start: function() {},
            _onReady: function() {},
            bestSelector: function() { return ''; },
            resolveElementCenter: function() { return null; },
            denormalizeSvgPath: function() { return ''; },
            typewriteHtml: function() {},
            animateCursor: function() { return null; },
            animateCursorWaypoints: function() { return null; },
            createHighlight: function() { return null; },
            createSvgEl: function() { return null; },
            onScrollResize: function() { return function() {}; },
            DEFAULTS: {},
        };
    }

    // === MOBILE DETECTION ===
    var MOBILE_BREAKPOINT = 600;
    function isMobile() { return window.innerWidth < MOBILE_BREAKPOINT; }

    // === Z-INDEX CONSTANTS ===
    var Z = {
        HIGHLIGHT: 9997,
        OVERLAY:   9998,
        CURSOR:    10000,
        PULSE:     10001,
        CONTAINER: 10002,
    };

    // === TIMING CONSTANTS ===
    var STEP_TRANSITION_MS = 300;


    // === CONFIGURATION DEFAULTS ===
    // Projects can override defaults via window.TutorialDefaults before loading
    var projectDefaults = (typeof window !== 'undefined' && window.TutorialDefaults) || {};
    var _hardDefaults = {
        avatar: '/images/Benoit helper.png',
        cursorImage: '/images/Cursor tutoriel.png',
        cursorSize: 'clamp(32px, 8vw, 48px)',
        mouthPosition: 0.39,
        avatarHeight: 'clamp(200px, 50vh, 475px)',
        avatarMaxHeight: '50vh',
        typewriteSpeed: 25,
        nextLabel: 'tutorial.next',
        doneLabel: 'tutorial.done',
        bubbleFontSize: 'clamp(14px, 3.5vw, 16px)',
        bubbleLineHeight: '1.6',
        bubbleMaxWidth: 'clamp(260px, 85vw, 550px)',
        bubbleMinWidth: 'clamp(200px, 70vw, 350px)',
        highlightColor: '#ffd700',
        highlightBorderRadius: 8,
    };
    var DEFAULTS = {};
    var k; for (k in _hardDefaults) DEFAULTS[k] = _hardDefaults[k];
    for (k in projectDefaults) DEFAULTS[k] = projectDefaults[k];

    // === FORCE HOVER STYLES ===

    /**
     * Force :hover CSS styles onto an element by scanning stylesheets for matching :hover rules
     * and applying them inline. Stores original values for cleanup.
     */
    // Shared stylesheet for injecting .tutorial-force-hover rules
    var _hoverStyleSheet = null;
    function getHoverStyleSheet() {
        if (!_hoverStyleSheet) {
            var styleEl = document.createElement('style');
            styleEl.setAttribute('data-tutorial-hover', '');
            document.head.appendChild(styleEl);
            _hoverStyleSheet = styleEl.sheet;
        }
        return _hoverStyleSheet;
    }

    function forceHoverStyles(el) {
        // Clean up any previously injected rules for this element
        removeForceHoverStyles(el);

        var sheet = getHoverStyleSheet();
        var injectedRules = [];

        try {
            var sheets = document.styleSheets;
            for (var i = 0; i < sheets.length; i++) {
                var rules;
                try { rules = sheets[i].cssRules || sheets[i].rules; } catch(e) { continue; }
                if (!rules) continue;
                for (var j = 0; j < rules.length; j++) {
                    var rule = rules[j];
                    if (!rule.selectorText || rule.selectorText.indexOf(':hover') === -1) continue;
                    // Check if element is the hovered target in any part of the selector
                    var selectorParts = rule.selectorText.split(',');
                    var matches = false;
                    for (var p = 0; p < selectorParts.length; p++) {
                        var part = selectorParts[p].trim();
                        if (part.indexOf(':hover') === -1) continue;
                        // Extract segment before :hover to check if el is the hovered element
                        var hoverSegment = part.split(':hover')[0].trim();
                        var lastSegment = hoverSegment.split(/[\s>+~]/).pop().trim();
                        try { if (lastSegment && el.matches(lastSegment)) { matches = true; break; } } catch(e) {}
                        // Also try direct match on full base selector
                        var baseSelector = part.replace(/:hover/g, '');
                        try { if (el.matches(baseSelector)) { matches = true; break; } } catch(e) {}
                    }
                    if (!matches) continue;
                    // Duplicate the rule replacing :hover with .tutorial-force-hover
                    var newSelector = rule.selectorText.replace(/:hover/g, '.tutorial-force-hover');
                    try {
                        var idx = sheet.insertRule(newSelector + ' { ' + rule.style.cssText + ' }', sheet.cssRules.length);
                        injectedRules.push(idx);
                    } catch(e) { /* skip invalid rules */ }
                }
            }
        } catch(e) {
            console.warn('TutorialEngine: forceHoverStyles failed', e);
        }
        // Store injected rule indices for cleanup
        el._tutorialHoverRules = injectedRules;
    }

    /**
     * Check if an element has visible hover effects by comparing computed styles
     * in normal state vs simulated :hover state.
     */
    function elementHasHoverEffect(el) {
        // Capture normal computed style snapshot
        var normalStyle = window.getComputedStyle(el);
        var propsToCheck = [
            'color', 'background-color', 'background', 'border-color',
            'box-shadow', 'text-decoration', 'opacity', 'transform',
            'outline', 'visibility', 'display'
        ];
        var normalValues = {};
        propsToCheck.forEach(function(p) { normalValues[p] = normalStyle.getPropertyValue(p); });

        // Temporarily inject a rule that forces :hover via a unique class
        var testClass = '_tut_hover_test_' + Date.now();
        var styleEl = document.createElement('style');
        document.head.appendChild(styleEl);
        // Copy all :hover rules that match this element
        var hasChange = false;
        try {
            var sheets = document.styleSheets;
            for (var i = 0; i < sheets.length; i++) {
                var rules;
                try { rules = sheets[i].cssRules || sheets[i].rules; } catch(e) { continue; }
                if (!rules) continue;
                for (var j = 0; j < rules.length; j++) {
                    var rule = rules[j];
                    if (!rule.selectorText || rule.selectorText.indexOf(':hover') === -1) continue;
                    var baseSelector = rule.selectorText.replace(/:hover/g, '');
                    try { if (!el.matches(baseSelector)) continue; } catch(e) { continue; }
                    var newSel = rule.selectorText.replace(/:hover/g, '.' + testClass);
                    try { styleEl.sheet.insertRule(newSel + '{' + rule.style.cssText + '}', styleEl.sheet.cssRules.length); } catch(e) {}
                }
            }

            el.classList.add(testClass);
            var hoverStyle = window.getComputedStyle(el);
            propsToCheck.forEach(function(p) {
                if (hoverStyle.getPropertyValue(p) !== normalValues[p]) hasChange = true;
            });
            el.classList.remove(testClass);
        } catch(e) {}
        document.head.removeChild(styleEl);

        // Fallback: check for Tailwind hover: classes or common hover class names
        if (!hasChange && el.className && typeof el.className === 'string') {
            if (el.className.match(/hover[:\\]|vlOpenOnHover|hover-/)) hasChange = true;
        }
        if (!hasChange && el.classList) {
            for (var ci = 0; ci < el.classList.length; ci++) {
                if (el.classList[ci].match(/hover[:\\]|vlOpenOnHover|hover-/)) { hasChange = true; break; }
            }
        }

        return hasChange;
    }

    /**
     * Walk up the DOM from a given element to find the closest ancestor (or self)
     * that has hover effects. Stops at <body>.
     */
    function findHoverableParent(el) {
        var current = el;
        while (current && current !== document.body) {
            if (elementHasHoverEffect(current)) return current;
            current = current.parentElement;
        }
        return null;
    }

    /**
     * Remove forced hover styles and restore original values.
     */
    function removeForceHoverStyles(el) {
        if (!el._tutorialHoverRules) return;
        var sheet = getHoverStyleSheet();
        // Remove in reverse order to keep indices valid
        var indices = el._tutorialHoverRules.slice().sort(function(a, b) { return b - a; });
        indices.forEach(function(idx) {
            try { sheet.deleteRule(idx); } catch(e) { /* already removed */ }
        });
        delete el._tutorialHoverRules;
    }

    // === STABILITY CHECKER ===

    /**
     * Wait for all highlight target elements to be stable (position/size not changing)
     * before calling the callback. Polls every 50ms, max 1.5s.
     */
    function waitForStableHighlight(highlightConfig, callback) {
        var allSelectors = [];
        (highlightConfig.groups || []).forEach(function(g) {
            var elems = g.elements || g;
            if (Array.isArray(elems)) elems.forEach(function(s) { allSelectors.push(s); });
        });

        var lastRects = null;
        var stableCount = 0;
        var maxAttempts = 30; // 30 * 50ms = 1.5s max
        var attempts = 0;

        function snapshot() {
            return allSelectors.map(function(sel) {
                var el = document.querySelector(sel);
                if (!el) return '0,0,0,0';
                var r = el.getBoundingClientRect();
                return Math.round(r.left) + ',' + Math.round(r.top) + ',' + Math.round(r.width) + ',' + Math.round(r.height);
            }).join('|');
        }

        function check() {
            attempts++;
            var current = snapshot();
            if (current === lastRects) {
                stableCount++;
            } else {
                stableCount = 0;
            }
            lastRects = current;

            // Stable for 3 consecutive checks (150ms) or max attempts reached
            var allVisible = current.indexOf('0,0,0,0') === -1;
            if ((stableCount >= 3 && allVisible) || attempts >= maxAttempts) {
                callback();
            } else {
                setTimeout(check, 50);
            }
        }

        // Start after a minimum initial delay for CSS transitions to begin
        setTimeout(check, 50);
    }

    // === UTILITY FUNCTIONS ===

    /**
     * Resolve an element's center position from a selector string.
     * Supports "screen:x,y" for absolute coordinates or a CSS selector.
     * @param {string} selector - CSS selector or "screen:x,y"
     * @param {Object} [anchor] - Optional {x, y} anchor ratios (0-1)
     * @returns {{x: number, y: number}|null}
     */
    /**
     * Resolve a reference selector like "highlight:0", "hover:1" into a real CSS selector
     * using the step's highlight/hover config.
     * @param {string} selector - A CSS selector, "screen:x,y", "highlight:N", or "hover:N"
     * @param {Object} [step] - Current step object for resolving references
     * @returns {string} Resolved CSS selector
     */
    function resolveRef(selector, step) {
        if (!step || typeof selector !== 'string') return selector;

        // highlight:N — resolve to the Nth element in highlight groups
        var hlMatch = selector.match(/^highlight:(\d+)$/);
        if (hlMatch && step.highlight && step.highlight.groups) {
            var hlIdx = parseInt(hlMatch[1], 10);
            var allHlSelectors = [];
            step.highlight.groups.forEach(function(g) {
                var elems = g.elements || g;
                if (Array.isArray(elems)) elems.forEach(function(s) { allHlSelectors.push(s); });
            });
            if (allHlSelectors[hlIdx]) return allHlSelectors[hlIdx];
            console.warn('TutorialEngine: highlight:' + hlIdx + ' not found, ' + allHlSelectors.length + ' elements available');
            return selector;
        }

        // hover:N — resolve to the Nth hover selector
        var hvMatch = selector.match(/^hover:(\d+)$/);
        if (hvMatch && step.hover) {
            var hvIdx = parseInt(hvMatch[1], 10);
            var hoverList = Array.isArray(step.hover) ? step.hover : [step.hover];
            if (hoverList[hvIdx]) return hoverList[hvIdx];
            console.warn('TutorialEngine: hover:' + hvIdx + ' not found, ' + hoverList.length + ' elements available');
            return selector;
        }

        return selector;
    }

    function resolveElementCenter(selector, anchor, step) {
        // Resolve references first
        selector = resolveRef(selector, step);

        if (typeof selector === 'string' && selector.indexOf('screen:') === 0) {
            var parts = selector.replace('screen:', '').split(',');
            return { x: parseFloat(parts[0]), y: parseFloat(parts[1]) };
        }
        var el = document.querySelector(selector);
        if (!el) {
            console.warn('TutorialEngine.resolveElementCenter: element not found for selector', selector);
            return null;
        }
        var rect = el.getBoundingClientRect();
        var ax = (anchor && anchor.x !== undefined) ? anchor.x : 0.5;
        var ay = (anchor && anchor.y !== undefined) ? anchor.y : 0.5;
        return {
            x: Math.max(0, Math.min(window.innerWidth, rect.left + rect.width * ax)),
            y: Math.max(0, Math.min(window.innerHeight, rect.top + rect.height * ay))
        };
    }

    /**
     * Convert a normalized SVG path (0-1 range) to absolute pixel coordinates.
     * @param {string} svgPath - Normalized SVG path string
     * @param {{x: number, y: number}} fromPos - Start position
     * @param {{x: number, y: number}} toPos - End position
     * @returns {string} Denormalized SVG path
     */
    function denormalizeSvgPath(svgPath, fromPos, toPos) {
        var dx = toPos.x - fromPos.x;
        var dy = toPos.y - fromPos.y;

        return svgPath.replace(/([-]?[0-9]*\.?[0-9]+)\s*,\s*([-]?[0-9]*\.?[0-9]+)/g, function(match, xStr, yStr) {
            var nx = parseFloat(xStr);
            var ny = parseFloat(yStr);
            var px = fromPos.x + nx * dx;
            var py = fromPos.y + ny * dy;
            return px.toFixed(1) + ',' + py.toFixed(1);
        });
    }

    // === CLICK PULSE ANIMATION ===

    /**
     * Create a single expanding ring element for click pulse effect.
     * @param {number} x - Center X
     * @param {number} y - Center Y
     * @param {number} bgOpacity - Background alpha (0-1)
     * @param {number} scale - Target scale
     * @param {number} duration - Animation duration in seconds
     * @param {number} delay - Animation delay in seconds
     */
    function createRing(x, y, bgOpacity, scale, duration, delay, color) {
        var c = color || '#ff4444';
        var rgb = c === '#4a6cf7' ? '74, 108, 247' : '255, 68, 68';
        var ring = document.createElement('div');
        Object.assign(ring.style, {
            position: 'fixed',
            left: x + 'px',
            top: y + 'px',
            width: '48px',
            height: '48px',
            borderRadius: '50%',
            border: '3px solid ' + c,
            backgroundColor: 'rgba(' + rgb + ', ' + bgOpacity + ')',
            pointerEvents: 'none',
            zIndex: String(Z.PULSE),
            transform: 'translate(-50%, -50%)',
        });
        document.body.appendChild(ring);

        gsap.to(ring, {
            scale: scale,
            opacity: 0,
            duration: duration,
            delay: delay,
            ease: 'power2.out',
            onComplete: function() { ring.remove(); }
        });
    }

    function createClickPulse(x, y) {
        var color = window._tutorialDevMode ? '#4a6cf7' : '#ff4444';
        createRing(x, y, 0.25, 3, 0.7, 0, color);
        createRing(x, y, 0.15, 2, 0.5, 0.15, color);
    }

    // === CURSOR ELEMENT FACTORY ===

    /**
     * Create a cursor <img> element positioned off-screen and invisible.
     * @param {Object} cfg - Cursor config with optional .image
     * @returns {HTMLImageElement}
     */
    function createCursorElement(cfg) {
        var cursorEl;
        if (isMobile()) {
            // On mobile: use a simple circle dot instead of mouse cursor image
            cursorEl = document.createElement('div');
            cursorEl.className = 'tutorial-cursor';
            var size = '24px';
            Object.assign(cursorEl.style, {
                position: 'fixed',
                zIndex: String(Z.CURSOR),
                width: size,
                height: size,
                borderRadius: '50%',
                backgroundColor: '#4a6cf7',
                border: '3px solid #ffffff',
                boxShadow: '0 2px 8px rgba(74,108,247,0.5)',
                pointerEvents: 'none',
                left: '0',
                top: '0',
                opacity: '0',
                transform: 'translate(-50%, -50%)',
            });
        } else {
            // On desktop: use cursor image
            cursorEl = document.createElement('img');
            cursorEl.src = cfg.image || '/images/tutorial-cursor.svg';
            cursorEl.className = 'tutorial-cursor';
            Object.assign(cursorEl.style, {
                position: 'fixed',
                zIndex: String(Z.CURSOR),
                width: DEFAULTS.cursorSize,
                height: DEFAULTS.cursorSize,
                pointerEvents: 'none',
                left: '0',
                top: '0',
                opacity: '0',
            });
        }
        document.body.appendChild(cursorEl);
        return cursorEl;
    }

    // === TYPEWRITER ENGINE ===

    /**
     * Typewrite HTML content into a container, node by node.
     * Clicking anywhere skips to full content.
     * @param {HTMLElement} container - Target DOM element
     * @param {string} html - HTML string to typewrite
     * @param {number} speed - Milliseconds per character
     * @param {Function} [callback] - Called when complete
     */
    function typewriteHtml(container, html, speed, callback, _parentSkipped) {
        var skipped = false;
        var callbackFired = false;

        function isSkipped() {
            return skipped || (_parentSkipped && _parentSkipped());
        }

        function fireCallback() {
            if (callbackFired) return;
            callbackFired = true;
            if (callback) callback();
        }

        var temp = document.createElement('div');
        temp.innerHTML = html;
        var nodes = Array.from(temp.childNodes);
        var nodeIndex = 0;

        if (!_parentSkipped) {
            var skipAll = function() {
                if (skipped) return;
                skipped = true;
                container.innerHTML = html;
                fireCallback();
            };

            document.addEventListener('click', skipAll, { once: true, capture: true });
            document.addEventListener('touchstart', skipAll, { once: true, capture: true });
        }

        function processNode() {
            if (isSkipped()) return;
            if (nodeIndex >= nodes.length) {
                if (!_parentSkipped) {
                    document.removeEventListener('click', skipAll, true);
                    document.removeEventListener('touchstart', skipAll, true);
                }
                fireCallback();
                return;
            }
            var node = nodes[nodeIndex];
            nodeIndex++;

            if (node.nodeType === Node.TEXT_NODE) {
                typewriteText(container, node.textContent, speed, processNode, isSkipped);
            } else if (node.nodeType === Node.ELEMENT_NODE) {
                var el = document.createElement(node.tagName);
                for (var i = 0; i < node.attributes.length; i++) {
                    el.setAttribute(node.attributes[i].name, node.attributes[i].value);
                }
                container.appendChild(el);
                if (node.childNodes.length > 0) {
                    typewriteHtml(el, node.innerHTML, speed, processNode, function() { return skipped || (_parentSkipped && _parentSkipped()); });
                } else {
                    processNode();
                }
            } else {
                processNode();
            }
        }
        processNode();
    }

    function typewriteText(container, text, speed, callback, isSkipped) {
        var i = 0;
        var span = document.createTextNode('');
        container.appendChild(span);

        function tick() {
            if (isSkipped && isSkipped()) return;
            if (i < text.length) {
                span.textContent += text.charAt(i);
                i++;
                setTimeout(tick, speed);
            } else {
                if (callback) callback();
            }
        }
        tick();
    }

    // === SVG ELEMENT FACTORY ===

    /**
     * Create an SVG element with the given tag and attributes.
     * @param {string} tag - SVG element tag name
     * @param {Object} [attrs] - Key-value pairs for attributes
     * @returns {SVGElement}
     */
    function createSvgEl(tag, attrs) {
        var el = document.createElementNS('http://www.w3.org/2000/svg', tag);
        if (attrs) { for (var k in attrs) el.setAttribute(k, attrs[k]); }
        return el;
    }

    // === HIGHLIGHT ENGINE ===

    /**
     * Create an SVG <rect> border element for a highlight cutout.
     * @param {{x: number, y: number, w: number, h: number, r: number}} cr - Cutout rect info
     * @returns {SVGRectElement}
     */
    function createBorderRect(cr) {
        return createSvgEl('rect', {
            x: cr.x,
            y: cr.y,
            width: cr.w,
            height: cr.h,
            rx: cr.r,
            fill: 'none',
            stroke: opts.highlightColor || '#ffd700',
            'stroke-width': '2',
        });
    }

    /**
     * Create a debounced scroll/resize listener pair.
     * @param {Function} callback - Called after debounce on scroll or resize
     * @returns {Function} cleanup - Removes listeners and clears timer
     */
    function onScrollResize(callback) {
        var rafId = null;
        function handler() {
            if (!rafId) {
                rafId = requestAnimationFrame(function() {
                    rafId = null;
                    callback();
                });
            }
        }
        window.addEventListener('scroll', handler, true);
        window.addEventListener('resize', handler);
        return function cleanup() {
            window.removeEventListener('scroll', handler, true);
            window.removeEventListener('resize', handler);
            if (rafId) { cancelAnimationFrame(rafId); rafId = null; }
        };
    }

    /**
     * Build SVG path data for highlight cutouts from groups config.
     * @param {Array} groups - Array of group objects with .elements arrays
     * @param {number} padding - Padding around elements in px
     * @param {number} radius - Border radius in px
     * @returns {{pathData: string, rects: Array}} Path data string and cutout rect info
     */
    function buildHighlightPathData(groups, padding, radius) {
        var W = window.innerWidth, H = window.innerHeight;
        var d = 'M 0,0 L ' + W + ',0 L ' + W + ',' + H + ' L 0,' + H + ' Z ';
        var cutoutRects = [];

        groups.forEach(function(group) {
            var selectors = group.elements || group;
            if (!selectors.length) return;
            var minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
            selectors.forEach(function(sel) {
                var el = document.querySelector(sel);
                if (!el) return;
                var rect = el.getBoundingClientRect();
                if (rect.left < minX) minX = rect.left;
                if (rect.top < minY) minY = rect.top;
                if (rect.right > maxX) maxX = rect.right;
                if (rect.bottom > maxY) maxY = rect.bottom;
            });
            if (minX === Infinity) return;
            var x = minX - padding, y = minY - padding;
            var w = (maxX - minX) + padding * 2, h = (maxY - minY) + padding * 2;
            var r = Math.min(radius, w / 2, h / 2);
            cutoutRects.push({ x: x, y: y, w: w, h: h, r: r });
            d += 'M ' + (x + r) + ',' + y
               + ' L ' + (x + w - r) + ',' + y
               + ' A ' + r + ',' + r + ' 0 0 1 ' + (x + w) + ',' + (y + r)
               + ' L ' + (x + w) + ',' + (y + h - r)
               + ' A ' + r + ',' + r + ' 0 0 1 ' + (x + w - r) + ',' + (y + h)
               + ' L ' + (x + r) + ',' + (y + h)
               + ' A ' + r + ',' + r + ' 0 0 1 ' + x + ',' + (y + h - r)
               + ' L ' + x + ',' + (y + r)
               + ' A ' + r + ',' + r + ' 0 0 1 ' + (x + r) + ',' + y + ' Z ';
        });

        return { pathData: d, rects: cutoutRects };
    }

    /**
     * Create a highlight overlay with cutouts around specified elements.
     * Supports legacy { selector: '...' } and new { groups: [...] } formats.
     * @param {Object} config - Highlight configuration
     * @returns {{el: SVGElement, refresh: Function, destroy: Function}|null}
     */
    function createHighlight(config) {
        if (!config) return null;
        // Backwards compat: convert old selector format to groups
        if (config.selector && !config.groups) {
            config = {
                groups: [{ elements: [config.selector] }],
                padding: config.padding || 8,
                borderRadius: config.borderRadius || 8,
            };
        }
        if (!config.groups || !config.groups.length) return null;
        var padding = config.padding || 8;
        var radius = config.borderRadius !== undefined ? config.borderRadius : (opts.highlightBorderRadius !== undefined ? opts.highlightBorderRadius : 8);

        var blockOutside = config.blockOutside || false;
        var hlOverlay = createSvgEl('svg', { width: '100%', height: '100%' });
        Object.assign(hlOverlay.style, {
            position: 'fixed', top: '0', left: '0', width: '100%', height: '100%', zIndex: String(Z.HIGHLIGHT), pointerEvents: 'none',
        });

        var result = buildHighlightPathData(config.groups, padding, radius);

        var path = createSvgEl('path', {
            d: result.pathData,
            fill: 'rgba(0,0,0,0.55)',
            'fill-rule': 'evenodd',
            'pointer-events': blockOutside ? 'fill' : 'none',
        });
        hlOverlay.appendChild(path);

        result.rects.forEach(function(cr) {
            hlOverlay.appendChild(createBorderRect(cr));
        });

        document.body.appendChild(hlOverlay);

        function refreshHighlight() {
            var W = window.innerWidth, H = window.innerHeight;
            hlOverlay.setAttribute('width', W);
            hlOverlay.setAttribute('height', H);
            var refreshResult = buildHighlightPathData(config.groups, padding, radius);
            path.setAttribute('d', refreshResult.pathData);

            // Rebuild border rects (handles count changes)
            var oldBorders = hlOverlay.querySelectorAll('rect');
            for (var i = 0; i < oldBorders.length; i++) oldBorders[i].remove();
            refreshResult.rects.forEach(function(cr) {
                hlOverlay.appendChild(createBorderRect(cr));
            });
        }

        // Auto-refresh on scroll/resize
        var cleanupScrollResize = onScrollResize(refreshHighlight);

        return {
            el: hlOverlay,
            refresh: refreshHighlight,
            destroy: function() {
                cleanupScrollResize();
                hlOverlay.remove();
            }
        };
    }

    // === CURSOR ENGINE ===

    /**
     * Launch cursor animation, choosing waypoints or simple based on step config.
     * @param {Object} step - Step config containing .cursor
     * @param {HTMLElement} overlay - Tutorial overlay element
     * @param {Function} [onComplete] - Called when animation finishes
     * @returns {{el: HTMLElement, timeline: Object, destroy: Function}|null}
     */
    function launchCursor(step, overlay, onComplete) {
        return step.cursor.waypoints
            ? animateCursorWaypoints(step, overlay, onComplete)
            : animateCursor(step, overlay, onComplete);
    }

    /**
     * Animate a cursor along an SVG path between two elements.
     * @param {Object} step - Step config containing .cursor
     * @param {HTMLElement} overlay - Tutorial overlay element
     * @param {Function} [onComplete] - Called when animation finishes
     * @returns {{el: HTMLElement, timeline: Object, destroy: Function}|null}
     */
    function animateCursor(step, overlay, onComplete) {
        var cfg = step.cursor;
        if (!cfg) {
            if (onComplete) onComplete();
            return null;
        }

        var fromPos = resolveElementCenter(cfg.from, cfg.fromAnchor, step);
        var toPos = resolveElementCenter(cfg.to, cfg.toAnchor, step);
        if (!fromPos || !toPos) {
            console.warn('TutorialEngine.animateCursor: could not resolve from/to selectors', cfg.from, cfg.to);
            if (onComplete) onComplete();
            return null;
        }

        var cursorEl = createCursorElement(cfg);

        var svgPathNorm = cfg.svgPath || 'M 0,0 C 0.3,0.1 0.7,0.9 1,1';
        var realPath = denormalizeSvgPath(svgPathNorm, fromPos, toPos);

        var timeline = gsap.timeline({
            onComplete: function() {
                if (cfg.click) {
                    var cursorRect = cursorEl.getBoundingClientRect();
                    var pulseX = cursorRect.left + cursorRect.width * 0.15;
                    var pulseY = cursorRect.top + cursorRect.height * 0.1;
                    createClickPulse(pulseX, pulseY);
                    gsap.to(cursorEl, {
                        scale: 0.7, duration: 0.1, yoyo: true, repeat: 1,
                        onComplete: function() {
                            if (!window._tutorialDevMode) {
                                var targetEl = document.querySelector(resolveRef(cfg.to, step));
                                if (targetEl && typeof targetEl.click === 'function') targetEl.click();
                            }
                            if (cfg.loop) {
                                gsap.set(cursorEl, { x: fromPos.x, y: fromPos.y, scale: 1 });
                                timeline.restart();
                            } else {
                                if (onComplete) onComplete();
                            }
                        }
                    });
                } else {
                    if (cfg.loop) {
                        gsap.set(cursorEl, { x: fromPos.x, y: fromPos.y });
                        timeline.restart();
                    } else {
                        if (onComplete) onComplete();
                    }
                }
            }
        });

        gsap.set(cursorEl, { x: fromPos.x, y: fromPos.y });
        timeline.to(cursorEl, { opacity: 1, duration: 0.3 });

        timeline.to(cursorEl, {
            duration: cfg.duration || 1.5,
            ease: cfg.ease || 'power2.inOut',
            motionPath: {
                path: realPath,
            }
        });

        if (cfg.delay) {
            timeline.delay(cfg.delay);
        }

        return {
            el: cursorEl,
            timeline: timeline,
            destroy: function() {
                timeline.kill();
                cursorEl.remove();
            }
        };
    }

    /**
     * Animate a cursor through multiple waypoints with optional click/hover actions.
     * Falls back to animateCursor if no waypoints defined.
     * @param {Object} step - Step config containing .cursor with .waypoints
     * @param {HTMLElement} overlay - Tutorial overlay element
     * @param {Function} [onComplete] - Called when animation finishes
     * @returns {{el: HTMLElement, timeline: Object, destroy: Function}|null}
     */
    function animateCursorWaypoints(step, overlay, onComplete) {
        var cfg = step.cursor;
        if (!cfg || !cfg.waypoints || !cfg.waypoints.length) {
            return animateCursor(step, overlay, onComplete);
        }

        var cursorEl = createCursorElement(cfg);

        var fromPos = resolveElementCenter(cfg.from, null, step);
        if (!fromPos) {
            cursorEl.remove();
            if (onComplete) onComplete();
            return null;
        }

        var timeline = gsap.timeline({
            delay: cfg.delay || 0,
            onComplete: function() {
                if (cfg.loop) {
                    gsap.set(cursorEl, { x: fromPos.x, y: fromPos.y });
                    timeline.restart();
                } else {
                    if (onComplete) onComplete();
                }
            }
        });

        gsap.set(cursorEl, { x: fromPos.x, y: fromPos.y });
        timeline.to(cursorEl, { opacity: 1, duration: 0.3 });

        var currentFrom = fromPos;
        cfg.waypoints.forEach(function(wp) {
            var wpPos = resolveElementCenter(wp.target, null, step);
            if (!wpPos) return;
            var wpPath = wp.svgPath || 'M 0,0 C 0.3,0.1 0.7,0.9 1,1';
            var realPath = denormalizeSvgPath(wpPath, currentFrom, wpPos);
            var segDuration = (cfg.duration || 1.5) / cfg.waypoints.length;

            timeline.to(cursorEl, {
                duration: segDuration,
                ease: cfg.ease || 'power2.inOut',
                motionPath: { path: realPath }
            });

            var action = wp.action || 'path';
            if (action === 'click') {
                timeline.call(function() {
                    var cr = cursorEl.getBoundingClientRect();
                    createClickPulse(cr.left + cr.width * 0.15, cr.top + cr.height * 0.1);
                });
                timeline.to(cursorEl, { scale: 0.7, duration: 0.1, yoyo: true, repeat: 1 });
                (function(target) {
                    timeline.call(function() {
                        if (!window._tutorialDevMode) {
                            var el = document.querySelector(target);
                            if (el) el.click();
                        }
                    });
                })(wp.target);
            } else if (action === 'hover') {
                (function(target) {
                    timeline.call(function() {
                        var el = document.querySelector(target);
                        if (el) {
                            forceHoverStyles(el);
                            el.classList.add('tutorial-force-hover');
                            el.dispatchEvent(new MouseEvent('mouseenter', { bubbles: true }));
                            el.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));
                        }
                    });
                    timeline.to(cursorEl, { duration: 0.5 });
                })(wp.target);
            }

            if (wp.pause && wp.pause > 0) {
                timeline.to(cursorEl, { duration: wp.pause });
            }

            currentFrom = wpPos;
        });

        return {
            el: cursorEl,
            timeline: timeline,
            destroy: function() {
                timeline.kill();
                cursorEl.remove();
            }
        };
    }

    // === START TUTORIAL (UI BUILDER) ===

    /**
     * Start the tutorial with given steps and options.
     * Builds the full UI (overlay, avatar, speech bubble) and drives step progression.
     * @param {Array} steps - Array of step config objects
     * @param {Object} [userOptions] - Override DEFAULTS
     */
    function start(steps, userOptions) {
        var opts = {};
        var k;
        for (k in DEFAULTS) opts[k] = DEFAULTS[k];
        if (userOptions) { for (k in userOptions) opts[k] = userOptions[k]; }
        // Store computed values for desktop reset after mobile override
        opts._bubblePadding = 'clamp(1rem, 3vw, 2rem) clamp(1.2rem, 4vw, 2.5rem)';

        var currentStep = 0;
        var activeCursor = null;
        var activeHighlight = null;

        // Preload avatar image
        var preload = new Image();
        preload.src = opts.avatar;

        preload.onload = function() {
            buildAndStart(preload.src);
        };
        preload.onerror = function() {
            console.warn('TutorialEngine: avatar image failed to load, proceeding with fallback', opts.avatar);
            buildAndStart(opts.avatar);
        };

        function buildAndStart(imgSrc) {
            var _linkNextCleanup = null;

            function cleanupLinkNext() {
                if (_linkNextCleanup) { _linkNextCleanup(); _linkNextCleanup = null; }
            }

            function setupLinkNext(step, actionBtn) {
                cleanupLinkNext();
                if (!step.linkNext) return;

                function attach(el) {
                    function onLinkedClick() {
                        // Fingerprint: capture wizard container state before click
                        var container = el.closest('.kompoModal, .vlModal, .vlPanel, form') || el.parentElement;
                        var fingerprint = '';
                        if (container) {
                            var headings = container.querySelectorAll('h1,h2,h3,h4,.text-xl,.text-2xl,.font-semibold,.miniTitle');
                            headings.forEach(function(h) { fingerprint += h.textContent.trim(); });
                            container.querySelectorAll('label').forEach(function(l) { fingerprint += l.textContent.trim(); });
                            var submitBtn = container.querySelector('button[type="submit"], .vlBtn');
                            if (submitBtn) fingerprint += submitBtn.textContent.trim();
                        }

                        // Poll for fingerprint change (wizard advanced) or timeout
                        var pollAttempts = 0;
                        var maxPoll = 20; // 20 * 200ms = 4s
                        function pollChange() {
                            pollAttempts++;
                            var newFingerprint = '';
                            var newContainer = document.querySelector(step.linkNext);
                            if (newContainer) newContainer = newContainer.closest('.kompoModal, .vlModal, .vlPanel, form') || newContainer.parentElement;
                            if (newContainer) {
                                var hs = newContainer.querySelectorAll('h1,h2,h3,h4,.text-xl,.text-2xl,.font-semibold,.miniTitle');
                                hs.forEach(function(h) { newFingerprint += h.textContent.trim(); });
                                newContainer.querySelectorAll('label').forEach(function(l) { newFingerprint += l.textContent.trim(); });
                                var sb = newContainer.querySelector('button[type="submit"], .vlBtn');
                                if (sb) newFingerprint += sb.textContent.trim();
                            }

                            if (newFingerprint !== fingerprint) {
                                // Wizard advanced, trigger next
                                actionBtn.click();
                            } else if (pollAttempts < maxPoll) {
                                setTimeout(pollChange, 200);
                            }
                            // If maxPoll reached with same fingerprint: validation error, do nothing
                        }
                        setTimeout(pollChange, 200);
                    }

                    el.addEventListener('click', onLinkedClick);
                    _linkNextCleanup = function() { el.removeEventListener('click', onLinkedClick); };
                }

                var targetEl = document.querySelector(step.linkNext);
                if (targetEl) {
                    attach(targetEl);
                } else {
                    // Wait for element to appear
                    var obs = new MutationObserver(function() {
                        var el = document.querySelector(step.linkNext);
                        if (el) { obs.disconnect(); attach(el); }
                    });
                    obs.observe(document.body, { childList: true, subtree: true });
                    _linkNextCleanup = function() { obs.disconnect(); };
                }
            }

            function cleanupAnimations() {
                if (activeCursor) { activeCursor.destroy(); activeCursor = null; }
                if (activeHighlight) { activeHighlight.destroy(); activeHighlight = null; }
                cleanupLinkNext();
                // Remove forced hover states
                document.querySelectorAll('.tutorial-force-hover').forEach(function(el) {
                    removeForceHoverStyles(el);
                    el.classList.remove('tutorial-force-hover');
                    el.dispatchEvent(new MouseEvent('mouseleave', { bubbles: true }));
                    el.dispatchEvent(new MouseEvent('mouseout', { bubbles: true }));
                });
            }

            // --- DOM Creation ---
            var domRefs = createTutorialDOM(imgSrc, opts);
            var overlay = domRefs.overlay;
            var container = domRefs.container;
            var bubble = domRefs.bubble;
            var arrow = domRefs.arrow;
            var textEl = domRefs.textEl;
            var optionsRow = domRefs.optionsRow;
            var btnRow = domRefs.btnRow;
            var actionBtn = domRefs.actionBtn;
            var pauseBtn = domRefs.pauseBtn;
            pauseBtn.addEventListener('click', function() {
                if (autoNextPaused) { resumeAutoNext(); } else { pauseAutoNext(); }
            });
            var backBtn = domRefs.backBtn;
            backBtn.addEventListener('click', function() {
                var step = steps[currentStep];
                var target = (typeof step.showBack === 'number') ? step.showBack : currentStep - 1;
                if (target >= 0) {
                    _lastDirection = -1;
                    clearAutoNext();
                    cleanupAnimations();
                    bubble.style.opacity = '0';
                    bubble.style.transform = 'scale(0.8)';
                    setTimeout(function() { showStep(target); }, STEP_TRANSITION_MS);
                }
            });
            var img = domRefs.img;
            var videoEl = domRefs.videoEl;

            document.body.appendChild(overlay);
            document.body.style.overflow = 'hidden';
            overlay.style.touchAction = 'none';

            // Mobile peek button — hides avatar+bubble temporarily to see behind
            var peekBtn = document.createElement('button');
            peekBtn.textContent = '\uD83D\uDC41';
            Object.assign(peekBtn.style, {
                position: 'fixed', zIndex: String(Z.CONTAINER + 1),
                bottom: '12px', right: '12px',
                width: '40px', height: '40px', borderRadius: '50%',
                border: 'none', backgroundColor: 'rgba(0,0,0,0.5)', color: '#fff',
                fontSize: '18px', cursor: 'pointer', display: 'none',
                alignItems: 'center', justifyContent: 'center',
                backdropFilter: 'blur(4px)', boxShadow: '0 2px 8px rgba(0,0,0,0.3)',
            });
            var peekHidden = false;
            peekBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                peekHidden = !peekHidden;
                container.style.display = peekHidden ? 'none' : 'flex';
                overlay.style.backgroundColor = peekHidden ? 'transparent' : (steps[currentStep].overlay !== false ? 'rgba(0,0,0,0.5)' : 'transparent');
                peekBtn.style.opacity = peekHidden ? '0.6' : '1';
            });
            peekBtn.addEventListener('touchstart', function(e) { e.stopPropagation(); }, { passive: true });
            document.body.appendChild(peekBtn);
            overlay.style.overscrollBehavior = 'contain';

            window.addEventListener('orientationchange', function() {
                setTimeout(function() { positionBubble(steps[currentStep].position || 'left', steps[currentStep].align || 'center', false, steps[currentStep].positionTarget); }, 300);
            });

            window.addEventListener('resize', function() {
                setTimeout(function() {
                    positionBubble(steps[currentStep].position || 'left', steps[currentStep].align || 'center', false, steps[currentStep].positionTarget);
                }, 100);
            });

            function resetContainerPosition() {
                container.style.position = 'relative';
                container.style.left = '';
                container.style.right = '';
                container.style.top = '';
                container.style.bottom = '';
                container.style.transform = '';
                container.style.maxHeight = '';
                container.style.overflow = '';
            }

            // Returns { availW, rect } or null if element not found
            function positionContainerAtTarget(targetSelector, side) {
                var el = document.querySelector(targetSelector);
                if (!el) return null;
                var rect = el.getBoundingClientRect();
                container.style.position = 'fixed';
                container.style.bottom = 'auto';
                container.style.right = 'auto';

                if (side === 'top' || side === 'bottom') {
                    var centerX = rect.left + rect.width / 2;
                    container.style.left = Math.max(8, centerX) + 'px';
                    container.style.transform = 'translateX(-50%)';
                    container.style.top = side === 'top'
                        ? Math.max(8, rect.top - 10) + 'px'
                        : (rect.bottom + 10) + 'px';
                    return { availW: Math.min(centerX, window.innerWidth - centerX) * 2 - 16, rect: rect };
                } else if (side === 'right') {
                    container.style.left = (rect.right + 10) + 'px';
                    container.style.top = Math.max(8, rect.top) + 'px';
                    container.style.transform = 'none';
                    return { availW: window.innerWidth - rect.right - 20, rect: rect };
                } else {
                    // left (default)
                    container.style.left = 'auto';
                    container.style.right = (window.innerWidth - rect.left + 10) + 'px';
                    container.style.top = Math.max(8, rect.top) + 'px';
                    container.style.transform = 'none';
                    return { availW: rect.left - 20, rect: rect };
                }
            }

            function startViewportClamp(hasChatMaxW) {
                _activeClampInterval = setInterval(function() {
                    if (!container.parentNode) { clearInterval(_activeClampInterval); _activeClampInterval = null; return; }
                    var br = bubble.getBoundingClientRect();
                    var vw = window.innerWidth;
                    var vh = window.innerHeight;
                    if (br.right > vw - 8) {
                        var newMax = vw - br.left - 16;
                        if (newMax < 200) {
                            container.style.left = '8px';
                            container.style.right = 'auto';
                            container.style.transform = 'none';
                            newMax = vw - 24;
                        }
                        if (!hasChatMaxW) {
                            bubble.style.maxWidth = Math.max(180, newMax) + 'px';
                            bubble.style.minWidth = Math.min(180, newMax) + 'px';
                        }
                    }
                    if (br.left < 8) {
                        container.style.left = '8px';
                        container.style.right = 'auto';
                        container.style.transform = 'none';
                    }
                    if (br.bottom > vh - 8) {
                        container.style.top = Math.max(8, parseInt(container.style.top) - (br.bottom - vh + 8)) + 'px';
                    }
                }, 100);
            }

            // Position bubble: arrow at mouth level, bubble grows upward
            // highlightBottom: true if highlighted element is in lower half of screen
            var _activeClampInterval = null;
            function positionBubble(side, align, highlightBottom, positionTarget) {
                // Clear any previous clamp interval
                if (_activeClampInterval) { clearInterval(_activeClampInterval); _activeClampInterval = null; }
                if (isMobile()) {
                    container.style.alignItems = 'center';
                    container.style.flexDirection = 'row';
                    // imgWrapper becomes a vertical flex container on mobile
                    domRefs.imgWrapper.style.display = 'flex';
                    domRefs.imgWrapper.style.flexDirection = 'column';
                    domRefs.imgWrapper.style.alignItems = 'center';
                    domRefs.imgWrapper.style.width = '100%';
                    // Reduce avatar to half size on mobile
                    img.style.height = 'clamp(120px, 30vh, 240px)';
                    img.style.maxHeight = '30vh';
                    img.style.alignSelf = 'center';
                    if (videoEl) {
                        videoEl.style.height = 'clamp(120px, 30vh, 240px)';
                        videoEl.style.maxHeight = '30vh';
                    }
                    // Reduce bubble on mobile
                    bubble.style.position = 'relative';
                    bubble.style.right = 'auto';
                    bubble.style.left = 'auto';
                    bubble.style.marginRight = '0';
                    bubble.style.marginLeft = '0';
                    bubble.style.bottom = 'auto';
                    bubble.style.top = 'auto';
                    bubble.style.padding = '0.8rem 1rem';
                    bubble.style.fontSize = '13px';
                    bubble.style.maxWidth = '90vw';
                    bubble.style.minWidth = '0';
                    bubble.style.width = '100%';
                    textEl.style.minHeight = '80px';
                    arrow.style.display = 'none';
                    overlay.style.justifyContent = 'center';
                    overlay.style.padding = '8px';

                    if (highlightBottom) {
                        // Highlight is in bottom half — avatar at TOP flipped, bubble BELOW avatar
                        overlay.style.alignItems = 'flex-start';
                        overlay.style.paddingTop = 'max(8px, env(safe-area-inset-top))';
                        overlay.style.paddingBottom = '8px';
                        img.style.transform = 'scaleY(-1)';
                        img.style.order = '1';
                        if (videoEl) { videoEl.style.transform = 'scaleY(-1)'; videoEl.style.order = '1'; }
                        bubble.style.order = '2';
                        bubble.style.marginTop = '8px';
                        bubble.style.marginBottom = '0';
                        bubble.style.transformOrigin = 'top center';
                    } else {
                        // Normal: bubble on top, avatar at BOTTOM
                        overlay.style.alignItems = 'flex-end';
                        overlay.style.paddingTop = '8px';
                        overlay.style.paddingBottom = 'max(8px, env(safe-area-inset-bottom))';
                        img.style.transform = 'none';
                        img.style.order = '3';
                        if (videoEl) { videoEl.style.transform = 'none'; videoEl.style.order = '3'; }
                        bubble.style.order = '1';
                        bubble.style.marginBottom = '8px';
                        bubble.style.marginTop = '0';
                        bubble.style.transformOrigin = 'bottom center';
                    }
                    return;
                }

                // Desktop: reset mobile overrides
                domRefs.imgWrapper.style.display = '';
                domRefs.imgWrapper.style.flexDirection = '';
                domRefs.imgWrapper.style.alignItems = '';
                domRefs.imgWrapper.style.width = '';
                img.style.transform = 'none';
                img.style.order = '';
                img.style.alignSelf = '';
                bubble.style.order = '';
                bubble.style.width = '';
                bubble.style.alignSelf = '';
                if (videoEl) { videoEl.style.transform = 'none'; videoEl.style.order = ''; }

                // Reset mobile overrides for desktop
                img.style.height = opts.avatarHeight;
                img.style.maxHeight = opts.avatarMaxHeight;
                if (videoEl) {
                    videoEl.style.height = opts.avatarHeight;
                    videoEl.style.maxHeight = opts.avatarMaxHeight;
                }

                var isChatMode = steps[currentStep] && steps[currentStep].chatMode;

                // Chat mode: bubble is relative, skip all avatar-based positioning
                if (isChatMode) {
                    bubble.style.position = 'relative';
                    bubble.style.top = 'auto';
                    bubble.style.bottom = 'auto';
                    bubble.style.left = 'auto';
                    bubble.style.right = 'auto';
                    bubble.style.transform = 'none';
                    bubble.style.marginLeft = '0';
                    bubble.style.marginRight = '0';
                    bubble.style.padding = opts._bubblePadding;
                    bubble.style.fontSize = opts.bubbleFontSize;
                    var chatStep = steps[currentStep];
                    bubble.style.maxWidth = chatStep.chatMaxWidth || opts.bubbleMaxWidth;
                    bubble.style.minWidth = opts.bubbleMinWidth;
                    textEl.style.minHeight = '0';
                    arrow.style.display = 'none';
                    container.style.alignItems = 'stretch';
                    container.style.overflow = '';
                    overlay.style.padding = '0';

                    if (positionTarget) {
                        var ptResult = positionContainerAtTarget(positionTarget, side);
                        if (ptResult) {
                            var availW = ptResult.availW;
                            container.style.maxHeight = '';
                            container.style.overflow = '';
                            // Constrain bubble: use chatMaxWidth if set, otherwise available space
                            var maxAvail = Math.max(200, Math.min(availW, window.innerWidth - 16));
                            if (chatStep.chatMaxWidth) {
                                bubble.style.maxWidth = chatStep.chatMaxWidth;
                                bubble.style.minWidth = 'auto';
                            } else {
                                bubble.style.maxWidth = maxAvail + 'px';
                                bubble.style.minWidth = Math.min(200, maxAvail) + 'px';
                            }
                            bubble.style.overflowWrap = 'break-word';
                            bubble.style.wordBreak = 'break-word';
                            bubble.style.boxSizing = 'border-box';
                            overlay.style.justifyContent = '';
                            overlay.style.alignItems = '';
                            // Continuously clamp bubble to viewport (typewriter changes size)
                            startViewportClamp(!!chatStep.chatMaxWidth);
                            // Initial constraint (only if no chatMaxWidth)
                            if (!chatStep.chatMaxWidth) {
                                bubble.style.maxWidth = maxAvail + 'px';
                                bubble.style.minWidth = Math.min(200, maxAvail) + 'px';
                            }
                            container.style.maxWidth = '';
                        }
                    } else {
                        resetContainerPosition();
                        // Chat mode without target: use position as vertical, align as horizontal
                        // position: top/left/right = top, bottom = bottom, else = center (vertical)
                        var vMap = { top: 'flex-start', bottom: 'flex-end' };
                        var hMap = { left: 'flex-start', center: 'center', right: 'flex-end' };
                        overlay.style.alignItems = vMap[side] || 'center';
                        overlay.style.justifyContent = hMap[align] || 'center';
                        overlay.style.padding = '16px';
                    }
                    return;
                }

                var imgHeight = img.offsetHeight;
                if (videoEl && videoEl.style.display !== 'none') {
                    imgHeight = videoEl.offsetHeight || imgHeight;
                }
                var mouthY = imgHeight * opts.mouthPosition;
                bubble.style.top = 'auto';
                bubble.style.bottom = (imgHeight - mouthY - 18) + 'px';
                bubble.style.transform = 'none';

                // Reset mobile overrides for desktop
                container.style.alignItems = 'flex-start';
                bubble.style.position = 'absolute';
                bubble.style.marginBottom = '0';
                bubble.style.padding = opts._bubblePadding;
                bubble.style.fontSize = opts.bubbleFontSize;
                bubble.style.maxWidth = opts.bubbleMaxWidth;
                bubble.style.minWidth = opts.bubbleMinWidth;
                textEl.style.minHeight = '0';
                arrow.style.display = '';
                // Reset arrow to default horizontal state (overridden by 'top' branch)
                arrow.style.bottom = '18px';
                arrow.style.transform = 'none';
                arrow.style.borderTop = '14px solid transparent';
                arrow.style.borderBottom = '14px solid transparent';
                overlay.style.padding = '0';

                // Horizontal alignment of the whole avatar+bubble group
                var alignMap = { left: 'flex-start', center: 'center', right: 'flex-end' };
                overlay.style.justifyContent = alignMap[align] || 'center';

                // Position target: anchor container near a DOM element
                if (positionTarget) {
                    var ptResult = positionContainerAtTarget(positionTarget, side);
                    if (ptResult) {
                        container.style.maxHeight = (window.innerHeight - 16) + 'px';
                        container.style.overflow = 'auto';
                        overlay.style.justifyContent = '';
                        overlay.style.alignItems = '';
                        // Clamp bubble within viewport (bubble is absolute, may overflow container)
                        requestAnimationFrame(function() {
                            var br = bubble.getBoundingClientRect();
                            var vw = window.innerWidth;
                            // If bubble overflows left, shift container right
                            if (br.left < 8) {
                                var shift = 8 - br.left;
                                var currentLeft = container.getBoundingClientRect().left;
                                container.style.left = (currentLeft + shift) + 'px';
                                container.style.right = 'auto';
                                container.style.transform = 'none';
                            }
                            // If bubble overflows right, shift container left
                            br = bubble.getBoundingClientRect();
                            if (br.right > vw - 8) {
                                var shift = br.right - (vw - 8);
                                var currentLeft = container.getBoundingClientRect().left;
                                container.style.left = (currentLeft - shift) + 'px';
                                container.style.right = 'auto';
                                container.style.transform = 'none';
                            }
                            // Clamp bottom
                            br = bubble.getBoundingClientRect();
                            if (br.bottom > window.innerHeight - 8) {
                                container.style.top = Math.max(8, parseInt(container.style.top) - (br.bottom - window.innerHeight + 8)) + 'px';
                            }
                        });
                    }
                } else {
                    resetContainerPosition();
                }

                if (side === 'top') {
                    // Bubble directly above avatar, centered horizontally on imgWrapper
                    container.style.flexDirection = 'row';
                    bubble.style.left = '0';
                    bubble.style.right = '0';
                    bubble.style.marginLeft = 'auto';
                    bubble.style.marginRight = 'auto';
                    bubble.style.bottom = (imgHeight + 10) + 'px';
                    bubble.style.top = 'auto';
                    bubble.style.transform = 'none';
                    bubble.style.transformOrigin = 'bottom center';
                    // Arrow points down toward avatar
                    arrow.style.left = '50%';
                    arrow.style.right = 'auto';
                    arrow.style.bottom = '-18px';
                    arrow.style.transform = 'translateX(-50%)';
                    arrow.style.borderLeft = '14px solid transparent';
                    arrow.style.borderRight = '14px solid transparent';
                    arrow.style.borderTop = '20px solid #ffffff';
                    arrow.style.borderBottom = 'none';
                } else if (side === 'right') {
                    // Bubble on right, avatar on left
                    container.style.flexDirection = 'row-reverse';
                    bubble.style.right = 'auto';
                    bubble.style.left = '100%';
                    bubble.style.marginRight = '0';
                    bubble.style.marginLeft = 'clamp(10px, 3vw, 20px)';
                    bubble.style.transformOrigin = 'bottom left';
                    arrow.style.right = 'auto';
                    arrow.style.left = '-18px';
                    arrow.style.borderLeft = 'none';
                    arrow.style.borderRight = '20px solid #ffffff';
                } else {
                    // Bubble on left (default), avatar on right
                    container.style.flexDirection = 'row';
                    bubble.style.left = 'auto';
                    bubble.style.right = '100%';
                    bubble.style.marginLeft = '0';
                    bubble.style.marginRight = 'clamp(10px, 3vw, 20px)';
                    bubble.style.transformOrigin = 'bottom right';
                    arrow.style.left = 'auto';
                    arrow.style.right = '-18px';
                    arrow.style.borderRight = 'none';
                    arrow.style.borderLeft = '20px solid #ffffff';
                }
            }

            var autoNextTimer = null;
            var autoNextBar = null;
            var autoNextTween = null;
            var autoNextPaused = false;
            var autoNextRemainingMs = 0;
            var autoNextStartedAt = 0;

            function clearAutoNext() {
                if (autoNextTimer) { clearTimeout(autoNextTimer); autoNextTimer = null; }
                if (autoNextTween) { autoNextTween.kill(); autoNextTween = null; }
                if (autoNextBar) { autoNextBar.remove(); autoNextBar = null; }
                autoNextPaused = false;
                autoNextRemainingMs = 0;
                if (pauseBtn) pauseBtn.style.display = 'none';
            }

            function pauseAutoNext() {
                if (!autoNextTimer || autoNextPaused) return;
                autoNextPaused = true;
                autoNextRemainingMs = Math.max(0, autoNextRemainingMs - (Date.now() - autoNextStartedAt));
                clearTimeout(autoNextTimer);
                autoNextTimer = -1; // marker: paused
                if (autoNextTween) autoNextTween.pause();
                pauseBtn.textContent = '\u25B6'; // ▶
                pauseBtn.setAttribute('aria-label', 'Resume');
            }

            function resumeAutoNext() {
                if (!autoNextPaused) return;
                autoNextPaused = false;
                autoNextStartedAt = Date.now();
                if (autoNextTween) autoNextTween.resume();
                autoNextTimer = setTimeout(function() {
                    actionBtn.click();
                }, autoNextRemainingMs);
                pauseBtn.textContent = '\u275A\u275A'; // ❚❚
                pauseBtn.setAttribute('aria-label', 'Pause');
            }

            /**
             * On mobile, Kompo tabs (<a role="tab" class="hidden md:block">) become
             * a dropdown (.vlTaggableInput). This helper detects if a selector targets
             * a hidden tab and returns the mobile dropdown alternative.
             * @param {string} selector - CSS selector to check
             * @returns {{isMobileTab: boolean, dropdownSelector: string, optionText: string}|null}
             */
            function resolveMobileTab(selector) {
                if (!isMobile()) return null;
                var el = document.querySelector(selector);
                if (!el) return null;
                // Check if element is a hidden tab link
                var isHiddenTab = el.tagName === 'A' && el.getAttribute('role') === 'tab' && getComputedStyle(el).display === 'none';
                if (!isHiddenTab) return null;
                // Find the parent .vlTabs container
                var tabsContainer = el.closest('.vlTabs');
                if (!tabsContainer) return null;
                // Extract the hash from the vlTabs id (responsive-tabs-{hash})
                var tabsId = tabsContainer.id || '';
                var hash = tabsId.replace('responsive-tabs-', '');
                // The mobile dropdown is a SIBLING of .vlTabs (not a child)
                // It contains input#tabs-select-{same hash}
                var tabsSelectInput = hash ? document.querySelector('input#tabs-select-' + hash) : null;
                // Fallback: search siblings
                if (!tabsSelectInput) {
                    var parent = tabsContainer.parentElement;
                    if (parent) tabsSelectInput = parent.querySelector('input[id^="tabs-select"]');
                }
                if (!tabsSelectInput) return null;
                // Walk up to the vlInputWrapper which is the visible dropdown
                var dropdownWrapper = tabsSelectInput.closest('.vlInputWrapper');
                if (!dropdownWrapper) return null;
                // The clickable part is the vlTaggableInput inside the wrapper
                var taggable = dropdownWrapper.querySelector('.vlTaggableInput');
                if (!taggable) return null;
                // Ensure the wrapper has a unique ID for CSS selector targeting
                if (!dropdownWrapper.id) {
                    dropdownWrapper.id = 'tutorial-mobile-tab-' + hash;
                }
                var tabText = el.textContent.trim();
                // Determine the tab index from the <a> position among all tab links
                var allTabLinks = Array.from(tabsContainer.querySelectorAll('a[role="tab"]'));
                var tabIndex = allTabLinks.indexOf(el);
                return {
                    isMobileTab: true,
                    dropdown: dropdownWrapper,
                    taggable: taggable,
                    selector: '#' + dropdownWrapper.id,
                    optionText: tabText,
                    tabIndex: tabIndex,
                    tabsContainer: tabsContainer,
                };
            }

            /**
             * Click the mobile tab dropdown and select the correct option.
             * @param {Object} mobileTab - Result from resolveMobileTab
             */
            function selectMobileTab(mobileTab) {
                if (!mobileTab || !mobileTab.isMobileTab) return;
                // Step 1: Click the select dropdown to open it
                mobileTab.taggable.click();
                // Step 2: Wait for options to render, then click the matching option
                setTimeout(function() {
                    var options = mobileTab.dropdown.querySelectorAll('.vlOption');
                    var clicked = false;
                    for (var i = 0; i < options.length; i++) {
                        if (options[i].textContent.trim() === mobileTab.optionText) {
                            options[i].click();
                            clicked = true;
                            break;
                        }
                    }
                    // Fallback: use Vue selectTab if click didn't work
                    if (!clicked) {
                        var vue = mobileTab.tabsContainer.__vue__;
                        if (vue && typeof vue.selectTab === 'function' && mobileTab.tabIndex >= 0) {
                            vue.selectTab(mobileTab.tabIndex);
                        }
                    }
                }, 200);
            }

            var _isAutoAdvancing = false;
            var _lastDirection = 1; // 1 = forward, -1 = backward

            function showStep(index) {
                currentStep = index;
                var step = steps[index];
                var isLast = index === steps.length - 1;

                // Conditional step: skip if element not found in DOM
                if (step.showIf && !document.querySelector(step.showIf)) {
                    var nextIdx = index + _lastDirection;
                    if (nextIdx >= 0 && nextIdx < steps.length) {
                        showStep(nextIdx);
                    }
                    return;
                }

                // Conditional step: skip if element IS found in DOM
                if (step.hideIf && document.querySelector(step.hideIf)) {
                    var nextIdx = index + _lastDirection;
                    if (nextIdx >= 0 && nextIdx < steps.length) {
                        showStep(nextIdx);
                    }
                    return;
                }

                // Notify path editor of step change
                overlay.dispatchEvent(new CustomEvent('tutorial-step-change', { detail: { index: index } }));

                // Cleanup auto-next from previous step
                clearAutoNext();

                // Scroll to element (scroll element into view)
                if (step.scrollTo) {
                    var scrollTarget = document.querySelector(step.scrollTo);
                    if (scrollTarget) {
                        scrollTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }

                // Scroll inside an element (scroll its content)
                if (step.scrollInside) {
                    var scrollEl = document.querySelector(step.scrollInside.selector);
                    if (scrollEl) {
                        var amount = step.scrollInside.to !== undefined ? step.scrollInside.to : step.scrollInside.by || 300;
                        var duration = step.scrollInside.duration || 1;
                        var target = step.scrollInside.to !== undefined ? amount : scrollEl.scrollTop + amount;
                        gsap.to(scrollEl, { scrollTop: target, duration: duration, ease: 'power2.inOut' });
                    }
                }

                // Silent click: click element and advance immediately, no UI shown
                // Skip silentClick when reached via auto-advance to prevent cascade chains
                // In dev mode: show the step normally with a badge instead of skipping
                if (step.silentClick && !_isAutoAdvancing && !window._tutorialDevMode) {
                    var silentEl = document.querySelector(step.silentClick);
                    if (silentEl && typeof silentEl.click === 'function') silentEl.click();
                    if (!isLast) {
                        showStep(currentStep + 1);
                    }
                    return;
                }
                // Reset auto-advancing flag after it has been checked
                _isAutoAdvancing = false;

                // Toggle overlay backdrop
                var showOverlay = step.overlay !== false;
                overlay.style.backgroundColor = showOverlay ? 'rgba(0, 0, 0, 0.5)' : 'transparent';
                overlay.style.pointerEvents = (showOverlay || step.clearOverlay) ? 'auto' : 'none';
                container.style.pointerEvents = 'auto';

                // Cleanup previous cursor and highlight
                cleanupAnimations();

                // Chat mode: show avatar inside bubble instead of beside it
                var chatAvatarEl = bubble.querySelector('.tutorial-chat-avatar');
                if (step.chatMode) {
                    img.style.display = 'none';
                    if (videoEl) videoEl.style.display = 'none';
                    arrow.style.display = 'none';
                    textEl.style.minHeight = '0';
                    if (!chatAvatarEl) {
                        chatAvatarEl = document.createElement('div');
                        chatAvatarEl.className = 'tutorial-chat-avatar';
                        Object.assign(chatAvatarEl.style, {
                            width: '100px',
                            height: '100px',
                            minWidth: '100px',
                            borderRadius: '50%',
                            overflow: 'hidden',
                            border: '3px solid #07499e',
                            flexShrink: '0',
                            alignSelf: 'center',
                        });
                        var chatImg = document.createElement('img');
                        chatImg.src = img.src;
                        Object.assign(chatImg.style, {
                            width: '100%',
                            height: '100%',
                            objectFit: 'cover',
                            objectPosition: 'top center',
                        });
                        chatAvatarEl.appendChild(chatImg);
                        // Insert before the arrow (first child)
                        bubble.insertBefore(chatAvatarEl, arrow);
                    }
                    chatAvatarEl.style.display = 'block';
                    // In chat mode, bubble must be relative (no avatar to anchor absolute positioning)
                    bubble.style.position = 'relative';
                    bubble.style.bottom = 'auto';
                    bubble.style.left = 'auto';
                    bubble.style.right = 'auto';
                    bubble.style.top = 'auto';
                    // Use CSS grid: avatar on the left, content stacked on the right
                    bubble.style.display = 'grid';
                    bubble.style.gridTemplateColumns = '100px 1fr';
                    bubble.style.gap = '1rem';
                    bubble.style.alignItems = 'start';
                    // Make all direct children except avatar span the right column
                    chatAvatarEl.style.gridRow = '1 / -1';
                    chatAvatarEl.style.gridColumn = '1';
                    // text, options, btnRow go to column 2
                    textEl.style.gridColumn = '2';
                    optionsRow.style.gridColumn = '2';
                    btnRow.style.gridColumn = '2';
                    arrow.style.gridColumn = '1 / -1';
                } else {
                    img.style.display = 'block';
                    if (chatAvatarEl) chatAvatarEl.style.display = 'none';
                    bubble.style.display = 'flex';
                    bubble.style.flexDirection = 'column';
                    bubble.style.alignItems = '';
                    bubble.style.gap = '';
                    textEl.style.gridColumn = '';
                    optionsRow.style.gridColumn = '';
                    btnRow.style.gridColumn = '';
                    arrow.style.gridColumn = '';
                }

                // Clear text and options
                textEl.innerHTML = '';
                optionsRow.innerHTML = '';
                optionsRow.style.display = 'none';
                btnRow.style.opacity = '0';
                btnRow.style.pointerEvents = 'none';
                actionBtn.textContent = isLast ? opts.doneLabel : opts.nextLabel;
                // Hide next/done button if step has options (options replace it)
                actionBtn.style.display = (step.options || step.linkNext) ? 'none' : '';
                // Show/hide back button
                backBtn.style.display = (step.showBack === true || typeof step.showBack === 'number') ? '' : 'none';

                // Mobile tab→dropdown auto-conversion
                // If highlight or cursor targets a hidden tab, swap to the dropdown
                if (isMobile()) {
                    // Check highlight selectors
                    if (step.highlight) {
                        var hlCfg = step.highlight;
                        var hlSel = hlCfg.selector || null;
                        if (!hlSel && hlCfg.groups && hlCfg.groups.length) {
                            var grp = hlCfg.groups[0];
                            var els = grp.elements || grp;
                            if (els.length) hlSel = els[0];
                        }
                        if (hlSel) {
                            var mTab = resolveMobileTab(hlSel);
                            if (mTab) {
                                // Replace highlight to target the dropdown instead
                                step = JSON.parse(JSON.stringify(step));
                                step.highlight = {
                                    groups: [{ elements: [mTab.selector] }],
                                    padding: (hlCfg.padding || 8),
                                    borderRadius: (hlCfg.borderRadius || 8),
                                };
                                // Auto-click: select this tab in the dropdown
                                selectMobileTab(mTab);
                            }
                        }
                    }
                    // Check cursor 'to' target
                    if (step.cursor && step.cursor.to) {
                        var cTab = resolveMobileTab(step.cursor.to);
                        if (cTab) {
                            step = typeof step._mobileCloned === 'undefined' ? JSON.parse(JSON.stringify(step)) : step;
                            step._mobileCloned = true;
                            step.cursor.to = cTab.selector;
                            step.cursor.click = false; // Don't do a raw click — we handle it ourselves
                            step._mobileTabToSelect = cTab; // Store for after cursor animation
                        }
                    }
                }

                // Auto-scroll to highlight element if it's off-screen
                var highlightBottom = false;
                var hlFirstEl = null;
                if (step.highlight) {
                    var hlConfig = step.highlight;
                    var firstSel = null;
                    if (hlConfig.selector) {
                        firstSel = hlConfig.selector;
                    } else if (hlConfig.groups && hlConfig.groups.length) {
                        var g = hlConfig.groups[0];
                        var elems = g.elements || g;
                        if (elems.length) firstSel = elems[0];
                    }
                    if (firstSel) {
                        hlFirstEl = document.querySelector(firstSel);
                        if (hlFirstEl) {
                            var hlRect = hlFirstEl.getBoundingClientRect();
                            // Scroll into view if element is outside visible area
                            if (hlRect.top < 0 || hlRect.bottom > window.innerHeight) {
                                // Temporarily allow scroll on body for scrollIntoView
                                document.body.style.overflow = '';
                                hlFirstEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                document.body.style.overflow = 'hidden';
                            }
                            // Recompute position after potential scroll
                            hlRect = hlFirstEl.getBoundingClientRect();
                            if (isMobile()) {
                                highlightBottom = (hlRect.top + hlRect.height / 2) > window.innerHeight / 2;
                            }
                        }
                    }
                }

                // Position & show bubble
                positionBubble(step.position || 'left', step.align || 'center', highlightBottom, step.positionTarget);
                bubble.style.opacity = '1';
                bubble.style.transform = 'scale(1)';

                // Show/hide peek button on mobile
                peekHidden = false;
                container.style.display = 'flex';
                peekBtn.style.display = isMobile() ? 'flex' : 'none';
                peekBtn.style.opacity = '1';

                // Force hover on elements for this step (before highlight so DOM updates first)
                if (step.hover) {
                    var hoverSelectors = Array.isArray(step.hover) ? step.hover : [step.hover];
                    hoverSelectors.forEach(function(sel) {
                        var el = document.querySelector(sel);
                        if (el) {
                            forceHoverStyles(el);
                            el.classList.add('tutorial-force-hover');
                            el.dispatchEvent(new MouseEvent('mouseenter', { bubbles: true }));
                            el.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));
                        }
                    });
                }

                // Launch highlight after hover elements are stable
                if (step.highlight) {
                    waitForStableHighlight(step.highlight, function() {
                        activeHighlight = createHighlight(step.highlight);
                    });
                }

                // Link page button to tutorial next (skip in dev mode)
                if (!window._tutorialDevMode) {
                    setupLinkNext(step, actionBtn);
                }

                // Typewrite from top to bottom
                setTimeout(function() {
                    var twSpeed = window._tutorialDevMode ? 0 : opts.typewriteSpeed;
                    typewriteHtml(textEl, step.html, twSpeed, function() {
                        btnRow.style.opacity = '1';
                        btnRow.style.pointerEvents = 'auto';
                        // Render option buttons
                        if (step.options && step.options.length) {
                            renderStepOptions(step.options, optionsRow, actionBtn, bubble, showStep, function() {
                                clearAutoNext();
                                cleanupAnimations();
                                overlay.remove();
                                peekBtn.remove();
                                document.body.style.overflow = '';
                            });
                        }
                        // Launch cursor animation after text is done (skip if afterAnimation handles it)
                        if (step.cursor && !step.afterAnimation) {
                            var cursorDelay = (step.cursor.delay || 0) * 1000;
                            setTimeout(function() {
                                // If mobile tab conversion stored a tab to select, do it after cursor finishes
                                var mobileTabCb = step._mobileTabToSelect ? function() {
                                    selectMobileTab(step._mobileTabToSelect);
                                } : null;
                                activeCursor = launchCursor(step, overlay, mobileTabCb);
                            }, cursorDelay);
                        }
                        // Auto-next: advance to next step after delay
                        if (step.autoNext && !isLast) {
                            var delay = (typeof step.autoNext === 'number') ? step.autoNext : 3;
                            var delayMs = delay * 1000;
                            // Show pause button
                            pauseBtn.style.display = '';
                            pauseBtn.textContent = '\u275A\u275A';
                            autoNextPaused = false;
                            autoNextRemainingMs = delayMs;
                            autoNextStartedAt = Date.now();
                            // Progress bar
                            autoNextBar = document.createElement('div');
                            Object.assign(autoNextBar.style, {
                                position: 'absolute',
                                bottom: '0',
                                left: '0',
                                height: 'clamp(3px, 0.8vw, 5px)',
                                width: '0%',
                                backgroundColor: '#07499e',
                                borderRadius: '0 0 16px 16px',
                                transition: 'none',
                            });
                            bubble.appendChild(autoNextBar);
                            autoNextTween = gsap.to(autoNextBar, {
                                width: '100%',
                                duration: delay,
                                ease: 'none',
                            });
                            if (window._tutorialDevMode) {
                                pauseAutoNext();
                            } else {
                                autoNextTimer = setTimeout(function() {
                                    _isAutoAdvancing = true;
                                    actionBtn.click();
                                }, delayMs);
                            }
                        }
                        // afterAnimation: auto-advance after cursor animation completes
                        // In dev mode: show Next button instead of auto-advancing
                        if (step.afterAnimation && !isLast) {
                            if (window._tutorialDevMode) {
                                // Dev mode: play animation but don't auto-advance
                                if (step.cursor) {
                                    activeCursor = launchCursor(step, overlay, function() {});
                                }
                            } else {
                                actionBtn.style.display = 'none';
                                var advanceFn = function() {
                                    _isAutoAdvancing = true;
                                    setTimeout(function() { actionBtn.click(); }, STEP_TRANSITION_MS);
                                };
                                if (step.cursor) {
                                    activeCursor = launchCursor(step, overlay, advanceFn);
                                } else {
                                    advanceFn();
                                }
                            }
                        }
                    });
                }, STEP_TRANSITION_MS);
            }

            // Button click: next step, redirect, or close
            actionBtn.addEventListener('click', function() {
                _lastDirection = 1;
                clearAutoNext();
                cleanupAnimations();
                var step = steps[currentStep];

                // If step has a redirect, navigate to that page
                if (step.redirect) {
                    window.location.href = step.redirect;
                    return;
                }

                if (currentStep < steps.length - 1) {
                    bubble.style.opacity = '0';
                    bubble.style.transform = 'scale(0.8)';
                    setTimeout(function() {
                        showStep(currentStep + 1);
                    }, STEP_TRANSITION_MS);
                } else {
                    overlay.remove();
                    peekBtn.remove();
                    document.body.style.overflow = '';
                }
            });

            // Start from specified step, autostart redirect, or first step
            var startFrom = opts.fromStep || 0;
            var autostart = localStorage.getItem('tutorial-autostart');
            if (autostart) {
                localStorage.removeItem('tutorial-autostart');
                try {
                    var parsed = JSON.parse(autostart);
                    if (parsed.fromStep !== undefined) startFrom = parsed.fromStep;
                } catch(e) {}
            }
            requestAnimationFrame(function() {
                showStep(startFrom);
            });

            // Fire _onReady callbacks for dev tools (always save context for late subscribers)
            var context = {
                overlay: overlay,
                container: container,
                steps: steps,
                showStep: showStep,
                activeCursor: function() { return activeCursor; },
                activeHighlight: function() { return activeHighlight; },
                setActiveCursor: function(val) { activeCursor = val; },
                setActiveHighlight: function(val) { activeHighlight = val; },
                actionBtn: actionBtn,
                bubble: bubble,
                textEl: textEl,
                optionsRow: optionsRow,
                currentStep: function() { return currentStep; },
                opts: opts,
            };
            _readyContext = context;
            _onReadyCallbacks.forEach(function(cb) { cb(context); });
        }

        /**
         * Create all tutorial DOM elements and return references.
         */
        function createTutorialDOM(imgSrc, opts) {
            // Overlay
            var overlay = document.createElement('div');
            overlay.id = 'tutorial-overlay';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.setAttribute('aria-label', 'Tutorial');
            Object.assign(overlay.style, {
                position: 'fixed',
                top: '0',
                left: '0',
                width: '100%',
                height: '100%',
                backgroundColor: 'rgba(0, 0, 0, 0.5)',
                zIndex: String(Z.OVERLAY),
                display: 'flex',
                alignItems: 'flex-end',
                justifyContent: 'center',
            });

            // Container
            var container = document.createElement('div');
            container.id = 'tutorial-container';
            Object.assign(container.style, {
                display: 'flex',
                alignItems: 'flex-start',
                maxWidth: '90vw',
                position: 'relative',
                zIndex: String(Z.CONTAINER),
            });

            // Character image wrapper
            var imgWrapper = document.createElement('div');
            Object.assign(imgWrapper.style, {
                position: 'relative',
                flexShrink: '0',
                alignSelf: 'flex-end',
            });

            var img = document.createElement('img');
            img.src = imgSrc;
            img.alt = 'Benoit';
            Object.assign(img.style, {
                height: opts.avatarHeight,
                maxHeight: opts.avatarMaxHeight,
                objectFit: 'contain',
                display: 'block',
                pointerEvents: 'none',
            });

            // Video avatar (plays first, then swaps to static image)
            var videoEl = null;
            if (opts.avatarVideo) {
                img.style.display = 'none';
                videoEl = document.createElement('video');
                videoEl.src = opts.avatarVideo;
                videoEl.autoplay = true;
                videoEl.muted = true;
                videoEl.playsInline = true;
                Object.assign(videoEl.style, {
                    height: opts.avatarHeight,
                    maxHeight: opts.avatarMaxHeight,
                    objectFit: 'contain',
                    display: 'block',
                    pointerEvents: 'none',
                });
                videoEl.addEventListener('ended', function() {
                    videoEl.style.display = 'none';
                    img.style.display = 'block';
                });
            }

            // Bubble
            var bubble = document.createElement('div');
            bubble.id = 'tutorial-bubble';
            bubble.setAttribute('role', 'status');
            bubble.setAttribute('aria-live', 'polite');
            Object.assign(bubble.style, {
                backgroundColor: '#ffffff',
                borderRadius: '16px',
                padding: 'clamp(1rem, 3vw, 2rem) clamp(1.2rem, 4vw, 2.5rem)',
                maxWidth: opts.bubbleMaxWidth,
                minWidth: opts.bubbleMinWidth,
                boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.25)',
                position: 'absolute',
                fontSize: opts.bubbleFontSize,
                lineHeight: opts.bubbleLineHeight,
                color: '#1a1a1a',
                fontFamily: '"Outfit", sans-serif',
                opacity: '0',
                transform: 'scale(0.8)',
                transformOrigin: 'bottom right',
                transition: 'opacity 0.3s ease, transform 0.3s ease',
                right: '100%',
                marginRight: 'clamp(8px, 2vw, 20px)',
                marginLeft: 'clamp(8px, 2vw, 20px)',
                display: 'flex',
                flexDirection: 'column',
            });

            // Arrow
            var arrow = document.createElement('div');
            Object.assign(arrow.style, {
                position: 'absolute',
                right: '-18px',
                bottom: '18px',
                width: '0',
                height: '0',
                borderTop: '14px solid transparent',
                borderBottom: '14px solid transparent',
                borderLeft: '20px solid #ffffff',
            });
            bubble.appendChild(arrow);

            // Text container
            var textEl = document.createElement('div');
            textEl.id = 'tutorial-text';
            Object.assign(textEl.style, {
                margin: '0',
                maxHeight: 'clamp(150px, 40vh, 300px)',
                overflowY: 'auto',
            });
            bubble.appendChild(textEl);

            // Options container (configurable per step)
            var optionsRow = document.createElement('div');
            optionsRow.id = 'tutorial-options';
            Object.assign(optionsRow.style, {
                marginTop: '1rem',
                display: 'none',
                flexDirection: 'column',
                gap: '0.5rem',
            });
            bubble.appendChild(optionsRow);

            // Button row
            var btnRow = document.createElement('div');
            Object.assign(btnRow.style, {
                marginTop: '1.2rem',
                display: 'flex',
                justifyContent: 'flex-end',
                gap: '0.5rem',
                opacity: '0',
                transition: 'opacity 0.3s ease',
            });

            var actionBtn = document.createElement('button');
            actionBtn.setAttribute('aria-label', 'Next step');
            Object.assign(actionBtn.style, {
                padding: '0.75rem 1.5rem',
                minHeight: '44px',
                backgroundColor: '#07499e',
                color: '#ffffff',
                border: 'none',
                borderRadius: '8px',
                cursor: 'pointer',
                fontSize: '14px',
                fontWeight: '600',
            });
            var pauseBtn = document.createElement('button');
            pauseBtn.textContent = '\u275A\u275A'; // ❚❚
            pauseBtn.setAttribute('aria-label', 'Pause');
            Object.assign(pauseBtn.style, {
                padding: '0.75rem 1rem',
                minHeight: '44px',
                backgroundColor: 'transparent',
                color: '#07499e',
                border: '2px solid #07499e',
                borderRadius: '8px',
                cursor: 'pointer',
                fontSize: '14px',
                fontWeight: '600',
                display: 'none',
            });
            // Click handler wired in start() where pause/resume functions are defined
            var backBtn = document.createElement('button');
            backBtn.textContent = '\u2190'; // ←
            backBtn.setAttribute('aria-label', 'Previous step');
            Object.assign(backBtn.style, {
                padding: '0.75rem 1rem',
                minHeight: '44px',
                backgroundColor: 'transparent',
                color: '#07499e',
                border: '2px solid #07499e',
                borderRadius: '8px',
                cursor: 'pointer',
                fontSize: '14px',
                fontWeight: '600',
                display: 'none',
            });
            btnRow.appendChild(backBtn);
            btnRow.appendChild(pauseBtn);
            btnRow.appendChild(actionBtn);
            bubble.appendChild(btnRow);

            imgWrapper.appendChild(bubble);
            if (videoEl) imgWrapper.appendChild(videoEl);
            imgWrapper.appendChild(img);
            container.appendChild(imgWrapper);
            overlay.appendChild(container);

            return {
                overlay: overlay,
                container: container,
                imgWrapper: imgWrapper,
                bubble: bubble,
                arrow: arrow,
                textEl: textEl,
                optionsRow: optionsRow,
                btnRow: btnRow,
                actionBtn: actionBtn,
                pauseBtn: pauseBtn,
                backBtn: backBtn,
                img: img,
                videoEl: videoEl,
            };
        }

        /**
         * Render option buttons for a step into the options row.
         */
        function renderStepOptions(options, optionsRow, actionBtn, bubble, showStep, closeTutorial) {
            function setOptBtnColors(btn, bg, color) {
                btn.style.backgroundColor = bg;
                btn.style.color = color;
            }

            optionsRow.style.display = 'flex';
            optionsRow.style.pointerEvents = 'none';
            var totalOpts = options.length;
            var renderedCount = 0;
            options.forEach(function(opt, oi) {
                var optBtn = document.createElement('button');
                optBtn.textContent = opt.label;
                var hoverBg = opt.hoverColor || '#07499e';
                var normalBg = opt.color || '#f0f4ff';
                var normalColor = opt.textColor || '#07499e';
                Object.assign(optBtn.style, {
                    padding: '0.75rem 1rem',
                    minHeight: '44px',
                    backgroundColor: normalBg,
                    color: normalColor,
                    border: '2px solid ' + (opt.borderColor || '#07499e'),
                    borderRadius: '10px',
                    cursor: 'pointer',
                    fontSize: '14px',
                    fontWeight: '600',
                    textAlign: 'left',
                    transition: 'background-color 0.2s ease, transform 0.1s ease',
                    opacity: '0',
                    transform: 'translateY(8px)',
                    pointerEvents: 'none',
                });
                optBtn.addEventListener('mouseenter', function() { setOptBtnColors(optBtn, hoverBg, '#ffffff'); });
                optBtn.addEventListener('mouseleave', function() { setOptBtnColors(optBtn, normalBg, normalColor); });
                optBtn.addEventListener('touchstart', function() { setOptBtnColors(optBtn, hoverBg, '#ffffff'); }, { passive: true });
                optBtn.addEventListener('touchend', function() { setOptBtnColors(optBtn, normalBg, normalColor); }, { passive: true });
                optBtn.addEventListener('click', function() {
                    if (opt.done) {
                        closeTutorial();
                    } else if (opt.redirect) {
                        if (opt.startTutorial !== undefined && opt.startTutorial !== false) {
                            localStorage.setItem('tutorial-autostart', JSON.stringify({
                                fromStep: typeof opt.startTutorial === 'number' ? opt.startTutorial : 0
                            }));
                        }
                        window.location.href = opt.redirect;
                    } else if (opt.goToStep !== undefined) {
                        bubble.style.opacity = '0';
                        bubble.style.transform = 'scale(0.8)';
                        setTimeout(function() { showStep(opt.goToStep); }, STEP_TRANSITION_MS);
                    } else {
                        // Default: advance to next step
                        actionBtn.click();
                    }
                });
                optionsRow.appendChild(optBtn);
                // Stagger animation, enable clicks only after all are visible
                gsap.to(optBtn, {
                    opacity: 1, y: 0, duration: 0.3,
                    delay: 0.1 * (oi + 1), ease: 'power2.out',
                    onComplete: function() {
                        optBtn.style.pointerEvents = 'auto';
                        renderedCount++;
                        if (renderedCount === totalOpts) {
                            optionsRow.style.pointerEvents = 'auto';
                        }
                    }
                });
            });
        }
    }

    // === ON READY HOOK (for external dev tools like Step Builder) ===
    var _onReadyCallbacks = [];
    var _readyContext = null;

    // === BEST SELECTOR (utility for dev tools) ===

    /**
     * Find the best unique CSS selector for a given DOM element.
     * @param {HTMLElement} el - Target element
     * @returns {string} CSS selector string
     */
    function bestSelector(el) {
        if (!el || el === document.body || el === document.documentElement) return 'body';
        if (el.id) return '#' + el.id;

        var i, sel, tag;

        function safeQSA(s) {
            try { return document.querySelectorAll(s).length; } catch(e) { return -1; }
        }

        // Filter out classes with special chars (Tailwind hover:, focus:, etc.)
        function safeClasses(classList) {
            var result = [];
            for (var j = 0; j < classList.length; j++) {
                var c = classList[j];
                if (!/[:%\[\]\/]/.test(c)) result.push(c);
            }
            return result;
        }

        // Try unique class combo
        var classes = safeClasses(el.classList);
        if (classes.length) {
            for (i = 0; i < classes.length; i++) {
                sel = '.' + classes[i];
                if (safeQSA(sel) === 1) return sel;
            }
            // Try tag + class
            tag = el.tagName.toLowerCase();
            for (i = 0; i < classes.length; i++) {
                sel = tag + '.' + classes[i];
                if (safeQSA(sel) === 1) return sel;
            }
        }
        // Try data attributes
        var attrs = el.attributes;
        for (i = 0; i < attrs.length; i++) {
            if (attrs[i].name.indexOf('data-') === 0) {
                var val = attrs[i].value.replace(/"/g, '\\"');
                sel = '[' + attrs[i].name + '="' + val + '"]';
                if (safeQSA(sel) === 1) return sel;
            }
        }
        // Try ancestor#id > relative path (short and stable)
        var ancestor = el.parentElement;
        while (ancestor && ancestor !== document.body) {
            if (ancestor.id) {
                // Build short path from ancestor to el
                var relPath = [];
                var cur = el;
                while (cur && cur !== ancestor) {
                    var p = cur.parentElement;
                    if (!p) break;
                    var sibs = Array.prototype.slice.call(p.children);
                    var idx = sibs.indexOf(cur) + 1;
                    var t = cur.tagName.toLowerCase();
                    // Try class-based segment first
                    var sc = safeClasses(cur.classList);
                    var found = false;
                    for (var ci = 0; ci < sc.length; ci++) {
                        var csel = t + '.' + sc[ci];
                        var full = '#' + ancestor.id + ' > ' + [csel].concat(relPath).join(' > ');
                        if (safeQSA(full) === 1) { relPath.unshift(csel); found = true; break; }
                    }
                    if (!found) relPath.unshift(t + ':nth-child(' + idx + ')');
                    cur = p;
                }
                sel = '#' + ancestor.id + ' > ' + relPath.join(' > ');
                if (safeQSA(sel) === 1) return sel;
            }
            ancestor = ancestor.parentElement;
        }

        // Fallback: nth-child path from body
        var path = [];
        var current = el;
        while (current && current !== document.body) {
            var parent = current.parentElement;
            if (!parent) break;
            var children = Array.prototype.slice.call(parent.children);
            var index = children.indexOf(current) + 1;
            tag = current.tagName.toLowerCase();
            path.unshift(tag + ':nth-child(' + index + ')');
            current = parent;
            sel = path.join(' > ');
            if (safeQSA(sel) === 1) return sel;
        }
        return path.join(' > ');
    }

    // === AUTO-START FROM REDIRECT ===
    // If tutorial was already viewed (PHP won't inject it), click help button to reset + reload
    $(function() {
        var autostart = localStorage.getItem('tutorial-autostart');
        if (autostart) {
            // Wait a moment — if start() runs, it will consume the flag
            setTimeout(function() {
                var flag = localStorage.getItem('tutorial-autostart');
                if (!flag) return; // start() already consumed it
                // Keep the flag for after reload, then click help to reset user setting
                var helpLink = document.querySelector('#intro-dashboard-help1 a');
                if (helpLink) helpLink.click();
            }, 2000);
        }
    });

    // === PUBLIC API ===
    return {
        start: start,
        DEFAULTS: DEFAULTS,

        resolveRef: resolveRef,
        resolveElementCenter: resolveElementCenter,
        denormalizeSvgPath: denormalizeSvgPath,
        typewriteHtml: typewriteHtml,
        animateCursor: animateCursor,
        animateCursorWaypoints: animateCursorWaypoints,
        createHighlight: createHighlight,
        forceHoverStyles: forceHoverStyles,
        removeForceHoverStyles: removeForceHoverStyles,
        findHoverableParent: findHoverableParent,
        createSvgEl: createSvgEl,
        onScrollResize: onScrollResize,
        bestSelector: bestSelector,
        _onReady: function(cb) {
            _onReadyCallbacks.push(cb);
            if (_readyContext) cb(_readyContext);
        },
    };
}
