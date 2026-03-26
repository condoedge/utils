// cards/position-card.js — Position card with visual layout adapted to normal vs chat mode

import { registerCard } from '../ui/cards';
import { el, makeInput, makeSelectorRow } from '../helpers';
import { iconEl } from '../icons';
import * as state from '../state';
import * as events from '../events';

// --- Segmented control builder ---
function makeSegmented(items, selected, onChange, style) {
    var row = el('div', { style: Object.assign({
        display: 'flex', gap: '2px', background: 'rgba(0,0,0,0.2)', borderRadius: '8px', padding: '2px',
    }, style || {}) });
    items.forEach(function(item) {
        var isActive = item.value === selected;
        var btn = el('div', { style: {
            flex: '1', textAlign: 'center', padding: '5px 2px', borderRadius: '6px', cursor: 'pointer',
            fontSize: '11px', fontWeight: '600', transition: 'all 0.15s',
            background: isActive ? 'rgba(108,138,255,0.25)' : 'transparent',
            color: isActive ? '#6c8aff' : '#7a7f8e',
            border: isActive ? '1px solid rgba(108,138,255,0.3)' : '1px solid transparent',
        }});
        if (item.icon) {
            var iconWrap = el('span', { style: { marginRight: '3px' } });
            iconWrap.textContent = item.icon;
            btn.appendChild(iconWrap);
            btn.appendChild(document.createTextNode(item.label));
        } else {
            btn.textContent = item.label;
        }
        btn.title = item.desc || item.value;
        btn.addEventListener('mouseenter', function() {
            if (!isActive) btn.style.background = 'rgba(255,255,255,0.06)';
        });
        btn.addEventListener('mouseleave', function() {
            if (!isActive) btn.style.background = 'transparent';
        });
        btn.addEventListener('click', function() { onChange(item.value); });
        row.appendChild(btn);
    });
    return row;
}

// --- Toggle pill builder ---
function makeTogglePill(label, active, onChange) {
    var pill = el('div', { style: {
        display: 'inline-flex', alignItems: 'center', gap: '6px',
        padding: '4px 10px', borderRadius: '20px', cursor: 'pointer',
        fontSize: '11px', fontWeight: '500', transition: 'all 0.15s',
        background: active ? 'rgba(108,138,255,0.2)' : 'rgba(255,255,255,0.04)',
        color: active ? '#6c8aff' : '#7a7f8e',
        border: active ? '1px solid rgba(108,138,255,0.3)' : '1px solid rgba(255,255,255,0.08)',
    }});
    var dot = el('div', { style: {
        width: '8px', height: '8px', borderRadius: '50%',
        background: active ? '#6c8aff' : 'rgba(255,255,255,0.15)',
        transition: 'all 0.15s',
    }});
    pill.appendChild(dot);
    pill.appendChild(document.createTextNode(label));
    pill.addEventListener('click', function() { onChange(!active); });
    return pill;
}

// --- Mini preview box ---
function makePreview(position, align, chatMode) {
    var box = el('div', { style: {
        width: '100%', height: '64px', borderRadius: '6px', position: 'relative',
        background: 'rgba(0,0,0,0.25)', border: '1px solid rgba(255,255,255,0.06)',
        marginBottom: '10px', overflow: 'hidden',
    }});

    // Avatar dot
    var avatar = el('div', { style: {
        width: '14px', height: '14px', borderRadius: '50%',
        background: '#2ecc71', position: 'absolute',
        transition: 'all 0.2s',
    }});

    // Bubble dot
    var bubble = el('div', { style: {
        width: chatMode ? '28px' : '22px', height: chatMode ? '14px' : '10px', borderRadius: '3px',
        background: '#6c8aff', position: 'absolute',
        transition: 'all 0.2s',
    }});

    if (chatMode) {
        // Chat mode: position = vertical, align = horizontal
        var vPos = position === 'top' ? '8px' : position === 'bottom' ? 'auto' : '50%';
        var vBottom = position === 'bottom' ? '8px' : 'auto';
        var vTransY = (position !== 'top' && position !== 'bottom') ? '-50%' : '0';
        var hPos = align === 'left' ? '8px' : align === 'right' ? 'auto' : '50%';
        var hRight = align === 'right' ? '8px' : 'auto';
        var hTransX = (align !== 'left' && align !== 'right') ? '-50%' : '0';

        bubble.style.top = vPos;
        bubble.style.bottom = vBottom;
        bubble.style.left = hPos;
        bubble.style.right = hRight;
        bubble.style.transform = 'translate(' + hTransX + ', ' + vTransY + ')';
        avatar.style.display = 'none'; // avatar inside bubble in chat mode
    } else {
        // Normal mode: align = horizontal screen position, position = bubble relative to avatar
        var hAlign = align || 'center';
        // Avatar horizontal position based on align
        var avatarLeft = hAlign === 'left' ? '15%' : hAlign === 'right' ? '85%' : '50%';
        avatar.style.left = avatarLeft;
        avatar.style.bottom = '8px';
        avatar.style.transform = 'translateX(-50%)';

        // Bubble relative to avatar
        var bTop = 'auto'; var bBottom = 'auto'; var bLeft = 'auto'; var bRight = 'auto'; var bTransform = '';
        if (position === 'left' || !position) {
            bRight = avatarLeft; bBottom = '10px'; bTransform = 'translateX(-8px)';
        } else if (position === 'right') {
            bLeft = avatarLeft; bBottom = '10px'; bTransform = 'translateX(8px)';
        } else if (position === 'top') {
            bLeft = avatarLeft; bBottom = '30px'; bTransform = 'translateX(-50%)';
        } else if (position === 'center') {
            bLeft = '50%'; bTop = '50%'; bTransform = 'translate(-50%, -50%)';
        }
        bubble.style.top = bTop; bubble.style.bottom = bBottom;
        bubble.style.left = bLeft; bubble.style.right = bRight;
        bubble.style.transform = bTransform;
    }

    box.appendChild(avatar);
    box.appendChild(bubble);

    // Legend
    if (!chatMode) {
        var legendAvatar = el('div', { style: { position: 'absolute', top: '4px', right: '4px', display: 'flex', gap: '6px', fontSize: '9px', color: '#7a7f8e' } });
        legendAvatar.appendChild(el('span', { innerHTML: '<span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#2ecc71;margin-right:2px"></span>Avatar' }));
        legendAvatar.appendChild(el('span', { innerHTML: '<span style="display:inline-block;width:8px;height:5px;border-radius:1px;background:#6c8aff;margin-right:2px"></span>Bulle' }));
        box.appendChild(legendAvatar);
    }

    return box;
}

registerCard({
    name: 'position',
    icon: 'position',
    title: 'Position',
    defaultVisible: true,
    hasData: function(step) {
        return !!(step.position || step.positionTarget || step.chatMode);
    },
    render: function(container, step) {
        var idx = state.getSelectedIndex();
        var isChatMode = !!step.chatMode;

        // Mini preview
        container.appendChild(makePreview(step.position || 'left', step.align || 'center', isChatMode));

        if (isChatMode) {
            // ========== CHAT MODE ==========
            // Position = vertical placement on screen
            container.appendChild(el('div', { textContent: 'Vertical', style: { fontSize: '10px', color: '#7a7f8e', textTransform: 'uppercase', letterSpacing: '0.3px', marginBottom: '4px' } }));
            container.appendChild(makeSegmented([
                { value: 'top', label: '↑ Top' },
                { value: 'center', label: '◎ Center' },
                { value: 'bottom', label: '↓ Bottom' },
            ], step.position || 'left', function(v) {
                state.updateStep(idx, 'position', v);
            }));

            // Align = horizontal placement on screen
            container.appendChild(el('div', { textContent: 'Horizontal', style: { fontSize: '10px', color: '#7a7f8e', textTransform: 'uppercase', letterSpacing: '0.3px', marginTop: '8px', marginBottom: '4px' } }));
            container.appendChild(makeSegmented([
                { value: 'left', label: '← Left' },
                { value: 'center', label: '◎ Center' },
                { value: 'right', label: 'Right →' },
            ], step.align || 'center', function(v) {
                state.updateStep(idx, 'align', v === 'center' ? undefined : v);
            }));

        } else {
            // ========== NORMAL MODE ==========
            // Align = avatar horizontal position on screen (left/center/right of page)
            container.appendChild(el('div', { textContent: 'Avatar on screen', style: { fontSize: '10px', color: '#7a7f8e', textTransform: 'uppercase', letterSpacing: '0.3px', marginBottom: '4px' } }));
            container.appendChild(makeSegmented([
                { value: 'left', label: '← Left' },
                { value: 'center', label: '◎ Center' },
                { value: 'right', label: 'Right →' },
            ], step.align || 'center', function(v) {
                state.updateStep(idx, 'align', v === 'center' ? undefined : v);
            }));

            // Position = bubble relative to avatar
            container.appendChild(el('div', { textContent: 'Bubble relative to avatar', style: { fontSize: '10px', color: '#7a7f8e', textTransform: 'uppercase', letterSpacing: '0.3px', marginTop: '8px', marginBottom: '4px' } }));
            container.appendChild(makeSegmented([
                { value: 'left', label: '← Left' },
                { value: 'right', label: 'Right →' },
                { value: 'top', label: '↑ Top' },
                { value: 'center', label: '◎ Center' },
            ], step.position || 'left', function(v) {
                state.updateStep(idx, 'position', v);
            }));
        }

        // positionTarget
        container.appendChild(el('div', { style: { marginTop: '10px' } }));
        container.appendChild(makeSelectorRow('Target', step.positionTarget, 'primary', function() {
            events.emit('pick-element', { callback: function(sel) {
                state.updateStep(idx, 'positionTarget', sel);
            }});
        }, step.positionTarget ? function() {
            state.updateStep(idx, 'positionTarget', undefined);
        } : null));

        // Toggles
        var togglesRow = el('div', { style: { display: 'flex', gap: '6px', marginTop: '8px', flexWrap: 'wrap' } });
        togglesRow.appendChild(makeTogglePill('chatMode', isChatMode, function(v) {
            state.updateStep(idx, 'chatMode', v || undefined);
        }));
        togglesRow.appendChild(makeTogglePill('clearOverlay', !!step.clearOverlay, function(v) {
            if (v) {
                state.updateStepBatch(idx, { clearOverlay: true, overlay: false });
            } else {
                state.updateStep(idx, 'clearOverlay', undefined);
            }
        }));
        container.appendChild(togglesRow);

        // chatMaxWidth (only in chat mode)
        if (isChatMode) {
            var chatRow = el('div', { style: {
                display: 'flex', alignItems: 'center', gap: '8px', marginTop: '6px',
                padding: '6px 10px', background: 'rgba(108,138,255,0.06)', borderRadius: '6px',
            }});
            chatRow.appendChild(el('span', { textContent: 'Max Width', style: { fontSize: '11px', color: '#7a7f8e' } }));
            var widthInp = makeInput(step.chatMaxWidth || '', function(v) {
                state.updateStep(idx, 'chatMaxWidth', v || undefined);
            }, { placeholder: '400px' });
            widthInp.style.flex = '1';
            chatRow.appendChild(widthInp);
            container.appendChild(chatRow);
        }
    },
});
