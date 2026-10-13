<?php

namespace Condoedge\Utils\Kompo\Files;

use Condoedge\Utils\Facades\FileModel;
use Condoedge\Utils\Kompo\Common\Form;

class SelectFileable extends Form
{
    protected $fileableType;
    protected $fileableId;

    public function created()
    {
        $this->fileableType = $this->prop('fileable_type');
        $this->fileableId = $this->prop('fileable_id');
    }

    public function render()
    {
        return _Select()->placeholder('files-type-fileable')
            ->options(
                FileModel::attachableRecordsQuery($this->fileableType)->get()->mapWithKeys(
                    fn ($model) => [$model->id => $model->display]
                )
            )->default($this->fileableId)
            ->name('fileable_id')->class('mb-10');
    }
}
