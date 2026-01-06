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
        if (!request('fileable_type') && !$this->defaultType) {
            return _Html('translate.files-select-type-fileable-first')->class('text-gray-600 italic');
        }

        return new SelectFileable(null, [
            'fileable_type' => request('fileable_type') ?: $this->defaultType,
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
