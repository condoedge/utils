// ui/cards.js — Card system for Step Builder v2
// Cards register here. This module renders them based on step data and state.

import { el } from '../helpers';
import { iconEl } from '../icons';
import * as state from '../state';
import * as events from '../events';

var _cards = [];       // Registered card definitions
var _container = null; // DOM container (detailZone)
var _rendering = false; // Prevent re-entry

// Card definition: { name, icon, title, alwaysVisible, defaultVisible, hasData(step), render(container, step) }
export function registerCard(cardDef) {
    _cards.push(cardDef);
}

export function init(container) {
    _container = container;
}

export function renderCards() {
    if (!_container || _rendering) return;
    _rendering = true;
    _container.innerHTML = '';
    var step = state.getCurrentStep();
    if (!step) return;

    _cards.forEach(function(cardDef) {
        var hasData = cardDef.hasData ? cardDef.hasData(step) : false;
        var isExpanded = state.isCardExpanded(cardDef.name);

        // Always show all cards. Expanded (on) if: alwaysVisible, has data, or user toggled on.
        var expanded = cardDef.alwaysVisible || hasData || isExpanded;
        renderCard(cardDef, step, expanded);
    });
    _rendering = false;
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
