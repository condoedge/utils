<?php

namespace Condoedge\Utils\Kompo\ContactInfo\Maps;

use Condoedge\Utils\Kompo\Common\Modal;

class AddressPlaceEditingForm extends Modal
{
	public $class = 'max-w-2xl overflow-y-auto mini-scroll';
	public $style = 'max-height:95vh';

	public $_Title = 'utils.manage-address';

	public function handle()
	{
		return [
			'address' => request('address'),
			'postal_code' => request('postal_code'),
			'city' => request('city'),
		];
	}

	public function headerButtons()
	{
		return _SubmitButton()->emitRoot($this->prop('event'))->closeModal();
	}

	public function body()
	{
		return [
			_Input('maps-address-address1')->name('address')->value(request('address')),
			_Columns(
				_Input('utils.maps-address-postal-code')->name('postal_code')->value(request('postal_code')),
				_Input('utils.maps-address-city')->name('city')->value(request('city')),
			),
		];
	}

	public function rules()
	{
		return [
			'address' => 'required',
			'postal_code' => 'required',
			'city' => 'required',
		];
	}
}
