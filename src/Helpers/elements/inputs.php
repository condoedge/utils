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

/**
 * @deprecated We fixed it overriding the whole component, so we keep the macro for backward compatibility, but it does nothing now.
 */
Dropdown::macro('dropdownOverModal', function ($id = null, $sumWidth = false, $heightAdd = 0) {
	return $this;
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
