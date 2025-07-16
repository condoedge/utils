<?php

namespace Condoedge\Utils\Kompo\Files;


class VideoPreview extends AbstractPreview
{
	public function render()
	{
		return _Video(fileRoute($this->fileType, $this->model->id))->class('max-h-screen');
	}
}