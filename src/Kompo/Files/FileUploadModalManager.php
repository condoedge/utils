<?php

namespace Condoedge\Utils\Kompo\Files;

use Condoedge\Utils\Kompo\Common\Modal;
use Condoedge\Utils\Facades\FileModel;

class FileUploadModalManager extends Modal
{
	public $class = 'overflow-y-auto mini-scroll';
	public $style = 'max-height:95vh; min-width: 350px;';

	protected $_Title = 'files-upload-one-multiple-files';
	protected $_Icon = 'document-text';

    protected $noHeaderButtons = true;
    public $model = FileModel::class;

	public function handle()
    {
        if(!$this->model->id) {
            FileModel::uploadMultipleFiles(request()->file('files'), $this->model->fileable_type, $this->model->fileable_id, request('tags'));
        } else {
            $this->model->fileable_type = request('fileable_type');
            $this->model->fileable_id = request('fileable_id');
            if(request('tags')) $this->model->tags()->sync(request('tags'));
            $this->model->save();
        }
	}

	public function body()
	{
		return _Rows(
            $this->model->id ? null : 
                _MultiFileWithJs()->name('files')->placeholder('files-upload-one-multiple-files')->class('text-gray-600 large-file-upload mb-0'),
            _Flex4(
                _MaxFileSizeMessage(),
                _MultiFileSizeCalculationDiv(),
            )->class('mb-10'),
            _Rows(
                _TagsMultiSelect(),
            ),

            _Collapsible(
                _Rows(
                    _Html('files-fileable')->class('text-lg font-semibold mb-2'),
                    new FileFileableForm($this->model->id),
                ),
            )->titleLabel('translate.advanced-options')
                ->titleElClass('!text-level1 !font-regular')
                ->class('mb-4'),

            _SubmitButton()->closeModal(),
        );
	}

	public function rules()
	{
        if($this->model->id) {
            return [];
        }
        return FileModel::attachmentsRules('files');
	}
}
