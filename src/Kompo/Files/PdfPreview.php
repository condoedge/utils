<?php

namespace Condoedge\Utils\Kompo\Files;

class PdfPreview extends AbstractPreview
{
	public function render()
	{
		return _Html('<embed src="'.fileRoute($this->fileType, $this->model->id).'" frameborder="0" width="100%" height="100%">')
			->style('height:95vh; width: 95vw');
	}
}
