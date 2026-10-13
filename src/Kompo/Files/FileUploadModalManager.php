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
        [$fileableType, $fileableId] = $this->pickedFileable();

        if(!$this->model->id) {
            FileModel::uploadMultipleFiles(request()->file('files'), $fileableType, $fileableId, request('tags'));
        } else {
            $this->model->fileable_type = $fileableType;
            $this->model->fileable_id = $fileableId;
            if(request('tags')) $this->model->tags()->sync(request('tags'));
            $this->model->save();
        }
	}

    /**
     * The [type, id] chosen in FileFileableForm, or nulls for a file with no parent.
     */
    protected function pickedFileable()
    {
        $records = FileModel::attachableRecordsQuery(request('fileable_type'));

        if (!$records || !request('fileable_id')) {
            return [null, null];
        }

        // Only a record the picker offers can be chosen.
        abort_unless($records->whereKey(request('fileable_id'))->exists(), 403);

        return [request('fileable_type'), request('fileable_id')];
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
            )->titleLabel('files-advanced-options')
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
