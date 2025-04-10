<?php

use Condoedge\Utils\Kompo\Elements\NumberRange;
use Kompo\Select;

Select::macro('overModal', function ($id) {
	return $this->id($id)
	->class('select-over-modal')
	->onFocus(fn($e) => $e->run('() => {
		let input =  $("#'. $id .'").closest(".vlTaggableInput");
		let inputWidth = input.width();
		let inputOffset = input.offset();
		let inputHeight = input.height();
		const selectContainer = $("#'. $id .'").closest(".select-over-modal");

		let style = selectContainer.attr("style") || "";
		style += "--select-translate:" + (inputOffset.top + inputHeight) + "px !important;";
		style += "--select-width:" + inputWidth + "px !important;";

		selectContainer.attr("style", style);
	}'));
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