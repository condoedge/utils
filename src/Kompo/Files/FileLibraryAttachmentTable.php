<?php

namespace Condoedge\Utils\Kompo\Files;

use Kompo\TableRow;

class FileLibraryAttachmentTable extends FileLibraryAttachmentQuery
{

    public $layout = 'Table';
    public $card = TableRow::class;
    public $isWhiteTable = true;

    public $itemsWrapperClass = 'w-max-4xl';

    public function headers()
    {
        return [
            _Th('translate.date'),
            _Th('translate.file'),
        ];
    }

	public function render($file)
	{
		return _TableRow(
            _HtmlDate($file->created_at),
            $file->linkEl()->class('word-break-all'),
        );
	}
}
