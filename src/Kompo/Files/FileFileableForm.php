<?php

namespace Condoedge\Utils\Kompo\Files;

use Condoedge\Utils\Facades\FileModel;
use Condoedge\Utils\Kompo\Common\Form;

class FileFileableForm extends Form
{
    public $style = 'max-height:95vh; min-width: 350px;';

    protected $defaultType;
    protected $defaultId;
    public $model = FileModel::class;

    public function created()
    {
        $this->defaultType = $this->model->fileable_type;
        $this->defaultId = $this->model->fileable_id;
    }

    public function render()
    {
        return _Columns(
            _Select()->placeholder('files-type-fileable')->options(
                FileModel::formattedTypesOptions(),
            )->default($this->defaultType)->name('fileable_type')
            ->selfGet('getSelectFileable')->inPanel1(),
            _Panel1(
                $this->getSelectFileable()
            ),
        );
    }

    public function getSelectFileable()
    {
        $fileableType = request('fileable_type') ?: $this->defaultType;

        if (!$fileableType) {
            return _Html('translate.files-select-type-fileable-first')->class('text-gray-600 italic');
        }

        // 'general' (files with no parent) is not a model: there is no record to pick.
        if (!FileModel::attachableRecordsQuery($fileableType)) {
            return null;
        }

        return new SelectFileable(null, [
            'fileable_type' => $fileableType,
            'fileable_id' => request('fileable_id') ?: $this->defaultId,
        ]);
    }

    public function rules()
    {
        return [
            // 'fileable_type' => 'required',
        ];
    }
}
