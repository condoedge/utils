<?php

namespace Condoedge\Utils\Kompo\Files;

use Condoedge\Utils\Kompo\Common\Modal;
use Condoedge\Utils\Facades\FileModel;

class FileForm extends Modal
{
    protected $fileableId;
    protected $fileableType;

    protected $_Title = 'files-upload-one-multiple-files';

    public function created()
    {
        $this->fileableId = $this->prop('fileable_id');

        $this->fileableType = $this->prop('fileable_type');
    }

	public function handle()
	{
        FileModel::uploadMultipleFiles(request()->file('files'), $this->fileableType, $this->fileableId);
	}

	public function body()
	{
		return [
			_Columns(
                _MultiFile()->name('files')->placeholder('files-upload-one-multiple-files')
                    ->class('text-gray-600'),
			),
		];
	}

	public function rules()
	{
		return [
			'files' => 'required',
        ];
	}
}
