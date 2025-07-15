<?php

namespace Condoedge\Utils\Kompo\Files;

class ImagePreview extends AbstractPreview
{
	public function render()
	{
		return _Img($this->model->name)->src(fileRoute($this->fileType, $this->model->id))->class('max-h-screen');
	}
}