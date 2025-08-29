<?php

namespace Condoedge\Utils\Kompo\Files;

use Condoedge\Utils\Kompo\Common\Form;
use Illuminate\Database\Eloquent\Relations\Relation;

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
        // I want to convert fileableType to a select option
        $model = Relation::morphMap()[$this->fileableType];
        $model = $model::query();

        return _Select()->placeholder('files-type-fileable')
            ->options(
                $model->forTeam(safeCurrentTeamId())->get()->mapWithKeys(
                    fn ($model) => [$model->id => $model->display]
                )
            )->default($this->fileableId)
            ->name('fileable_id')->class('mb-10');
    }
}
