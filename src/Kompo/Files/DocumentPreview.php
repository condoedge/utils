<?php

namespace Condoedge\Utils\Kompo\Files;

class DocumentPreview extends AbstractPreview
{
	public function render()
	{
		return _DocxPreview(fileRoute($this->fileType, $this->model->id))
			->style('height:95vh; width: 95vw');
	}
}
