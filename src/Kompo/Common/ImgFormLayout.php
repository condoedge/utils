<?php

namespace Condoedge\Utils\Kompo\Common;

use \Condoedge\Utils\Kompo\Common\Form;

abstract class ImgFormLayout extends Form
{
    public $containerClass = '';

    protected $imgUrl = 'images/left-column-image.png';

    protected $rightColumnBodyWrapperClass = 'flex-1 justify-around md:justify-center';

	public function render()
	{
        $this->class(config('kompo-auth.img_form_layout_default_class'));

		return _Columns(
            _Div(
                _Img(asset($this->imgUrl))->class('h-screen w-full')->bgCover(),
            )->class('relative hidden md:block')->col('col-md-7'),
            _Rows(
                _LocaleSwitcher()->class('absolute top-0 right-0'),
                _Rows(
                    $this->rightColumnBody(),
                )->class('p-6 md:p-8 w-full')->class($this->rightColumnBodyWrapperClass)->style('max-width:500px'),
            )->class('items-center h-screen overflow-auto mini-scroll')
            ->col('col-12 col-md-5 bg-greenmain'),
		)->class('no-gutters');
	}
}
