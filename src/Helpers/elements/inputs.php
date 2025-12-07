<?php

use Condoedge\Utils\Kompo\Elements\NumberRange;
use Kompo\Dropdown;
use Kompo\Rows;
use Kompo\Select;

Select::macro('overModal', function ($id = null) {
	$id = $id ?? (class_basename($this) . \Str::random(5) . time());

	return $this->id($id)
		->class('select-over-modal')
		->onFocus(fn($e) => $e->run('() => {
			let input =  $("#' . $id . '").closest(".vlTaggableInput");
			let inputWidth = input.width();
			let inputOffset = input.offset();
			let inputHeight = input.height();
			const selectContainer = $("#' . $id . '").closest(".select-over-modal");

			let style = selectContainer.attr("style") || "";
			style += "--select-translate:" + (inputOffset.top + inputHeight) + "px !important;";
			style += "--select-width:" + inputWidth + "px !important;";

			selectContainer.attr("style", style);
		}'));
});

Dropdown::macro('dropdownOverModal', function ($id = null, $sumWidth = false, $heightAdd = 0) {
	$id = $id ?: (class_basename($this) . \Str::random(5) . time());

	$this->elements = array_merge($this->elements ?? [], [
		_Hidden()->onLoad(fn($e) => $e->run('() => {
			const dropdownListenerEl = $("#' . $id . '");

			if (!dropdownListenerEl.length) {
				return;
			}

			const dropdownHost = dropdownListenerEl.get(0);

			if (!dropdownHost) {
				return;
			}

			const bumpZIndex = () => {
				window.__kompoDropdownOverModalZIndex = (window.__kompoDropdownOverModalZIndex || 200) + 1;
				dropdownHost.style.setProperty("z-index", window.__kompoDropdownOverModalZIndex, "important");
			};

			function setTranslate() {
				const dropdownMenu = dropdownListenerEl.find(".vlDropdownMenu");
				const dWidth = dropdownMenu.outerWidth() || dropdownListenerEl.outerWidth() || 0;
				const dOffset = dropdownListenerEl.offset() || { top: 0, left: 0 };
				const dHeight = dropdownListenerEl.outerHeight() || 0;

				bumpZIndex();

				dropdownHost.style.setProperty("--dropdown-translate-y", (dOffset.top + dHeight + ' . $heightAdd . ') + "px", "important");

				const rightDistance = window.innerWidth - (dOffset.left + ' . ($sumWidth ? 'dWidth' : '20') . ');
				dropdownHost.style.setProperty("--dropdown-translate-x", "-" + Math.abs(rightDistance - 30) + "px", "important");
			}

			dropdownListenerEl.addClass("dropdown-over-modal");
			
			// Remove hover-based opening, we will control it via JS
			dropdownListenerEl.removeClass("vlOpenOnHover");

			const dropdownMenu = dropdownListenerEl.find(".vlDropdownMenu");
			let isOverTrigger = false;
			let isOverMenu = false;
			let closeTimeout;

			function showMenu() {
				clearTimeout(closeTimeout);
				setTranslate();
				dropdownMenu.css({ 
					opacity: 1, 
					transform: "translate(var(--dropdown-translate-x), var(--dropdown-translate-y)) scale(1)",
					pointerEvents: "auto"
				});
			}

			function hideMenu() {
				closeTimeout = setTimeout(() => {
					if (!isOverTrigger && !isOverMenu) {
						dropdownMenu.css({ 
							opacity: 0, 
							transform: "translate(var(--dropdown-translate-x), var(--dropdown-translate-y)) scale(0)",
							pointerEvents: "none"
						});
					}
				}, 100);
			}

			// Initial state
			dropdownMenu.css({ opacity: 0, transform: "scale(0)", pointerEvents: "none" });

			// Trigger hover
			dropdownListenerEl.on("mouseenter", () => {
				isOverTrigger = true;
				showMenu();
			});
			dropdownListenerEl.on("mouseleave", () => {
				isOverTrigger = false;
				hideMenu();
			});

			// Menu hover
			dropdownMenu.on("mouseenter", () => {
				isOverMenu = true;
				showMenu();
			});
			dropdownMenu.on("mouseleave", () => {
				isOverMenu = false;
				hideMenu();
			});

			// Hide on scroll
			window.addEventListener("scroll", () => {
				isOverTrigger = false;
				isOverMenu = false;
				dropdownMenu.css({ opacity: 0, transform: "scale(0)", pointerEvents: "none" });
			}, true);
	}')),
	]);
	
	return $this->id($id);
});

Dropdown::macro('maxHeightWithScroll', function ($height = '30rem') {
	return $this->class('dropdown-with-scroll')
		->addStyle("--dropdown-max-height: $height !important;");
});

Rows::macro('balloonOver', function ($id = null) {
	$id = $id ?? (class_basename($this) . \Str::random(5));

	$balloonPatchEl = _Hidden()->onLoad(fn($e) => $e->run('() => {
			let balloonContainer = document.querySelector("#' . $id . '");
			
			' . file_get_contents(__DIR__ . '/../../../resources/js/balloon-patch.js') . '
	}'));

	$this->elements = array_merge($this->elements ?? [], [$balloonPatchEl]);

	return $this->class('balloon-patch')
		->id($id);
});

if (!function_exists('_ColorPicker')) {
	function _ColorPicker($label = '')
	{
		return _Input($label)->type('color');
	}
}

if (!function_exists('_InputEmail')) {
	function _InputEmail($label = '')
	{
		return _Input($label)->type('email');
	}
}

if (!function_exists('_NumberRange')) {
	function _NumberRange()
	{
		return NumberRange::form();
	}
}

if (!function_exists('_InputPhone')) {
	function _InputPhone($label = 'utils.phone')
	{
		return _ValidatedInput($label)
			->type('tel')
			->formatModels([
				'^(\d{3})(\d{3})(.*)' => '$1-$2-$3',
			])
			->allow('^\d{0,10}$')
			->validate('^\d{3}(?:[-\s]?\d{3})(?:[-\s]?\d{4})$');
	}
}
