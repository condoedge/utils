<?php

namespace Condoedge\Utils\Kompo\Files;

use Condoedge\Utils\Facades\FileModel;
use Condoedge\Utils\Kompo\Common\Form;

class FileInfo extends Form
{
    public $model = FileModel::class;

    public function render()
    {
        return _CardWhite(
            _Html(__('files-file-name', ['name' => $this->model->name]))->class('text-sm font-bold mb-1'),
            _Html(__('files-file-size', ['size' => $this->model->size]))->class('text-sm'),
            _Flex(
                _Html(__('files-file-uploaded-at'))->class('text-sm'),
                $this->model->uploadedAt()->class('!text-sm !text-black'),
            )->class('gap-2'),
            $this->fileableInfo(),
            !auth()->user()->can('viewFileOf', $this->model->fileable) ? null : _Button('files-edit-form')
                ->selfGet('getFileForm', ['id' => $this->model->id])->inModal()
                ->class('mt-4'),
            // _Html(__('file.file-uploaded-by') . ': ' . $file->uploadedBy())->class('text-sm'),
        )->class('pr-12 pl-6 py-4 min-w-[25%]');
    }

    public function getFileForm($id)
    {
        return new FileUploadModalManager($id);
    }

    protected function fileableInfo()
    {
        if(!$this->model->fileable) return null;

        return _Rows(
            _Html(__('files-fileable-type', ['type' => $this->model->fileable_type]))->class('text-sm'),
            _Html(__('files-fileable-id', ['id' => $this->model->fileable->id]))->class('text-sm'),
        );
    }
}
