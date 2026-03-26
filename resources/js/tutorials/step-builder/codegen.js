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
