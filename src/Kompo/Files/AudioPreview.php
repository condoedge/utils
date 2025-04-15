<?php

namespace Condoedge\Utils\Kompo\Files;

class AudioPreview extends AbstractPreview
{

	public function render()
	{
		return _Audio(fileRoute($this->model->fileType, $this->model->id));
	}
}