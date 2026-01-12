<?php

namespace Condoedge\Utils\Kompo\Files;


class RawDocumentPreview extends AbstractPreview
{
	public function render()
	{
		return _Html(
            '<embed src="'.fileRoute($this->fileType, $this->model->id).'" frameborder="0" width="100%" height="100%">'
        )->class('p-4')->style('height:55vh; width: 60vw');
	}
}