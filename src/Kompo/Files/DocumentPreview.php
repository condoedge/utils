<?php

namespace Condoedge\Utils\Kompo\Files;

class DocumentPreview extends AbstractPreview
{
	public function render()
	{
		return _DocxPreview(fileRoute($this->fileType, $this->model->id))
			->style('height: 90vh; width: min(95vw, 900px); max-width: 100%;');
	}
}
