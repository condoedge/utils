<?php

namespace Condoedge\Utils\Kompo\Files;

use Condoedge\Utils\Facades\FileModel;
use Condoedge\Utils\Kompo\Common\Query;

class FilesCardTable extends Query
{
    protected $teamId;
    protected $fileableId;
    protected $fileableType;

    protected $fileable;

    public function created()
    {
        $this->teamId = currentTeamId();
        $this->fileableId = $this->prop('fileable_id');
        $this->fileableType = $this->prop('fileable_type');

		$this->perPage = $this->prop('limit') ?? 10;
		$this->hasPagination = $this->prop('has_pagination');

        $this->fileable = findOrFailMorphModel($this->fileableId, $this->fileableType);
    }

	public function query()
	{
		return FileModel::where('team_id', $this->teamId)
			->where('fileable_type', $this->fileableType)
			->where('fileable_id', $this->fileableId)
			->orderByDesc('created_at');
	}

	public function top()
	{
		return _FlexBetween(
            _TitleCard('files.files'),
            _CreateCard()->selfCreate('getFileForm')->inModal(),
        )->class('mb-2');
	}

	public function render($file)
	{
		return _FlexBetween(
			_Flex(
				$file->thumb?->class('shrink-0 text-greenmain opacity-60 mr-1'),
				_Rows(
					_Html($file->name),
					_Html($file->created_at->diffForHumans() . ' - ' . sizeAsKb($file->size))->class('text-sm text-geenmain opacity-50'),
				),
			)->class('gap-4'),
        	_Delete($file),
       )->class('py-3')->selfUpdate('getFileActionsModal', ['id' => $file->id])->inModal();
	}

	public function getFileForm($id = null)
    {
        return new FileForm($id, [
        	'fileable_id' => $this->fileableId,
        	'fileable_type' => $this->fileableType,
        ]);
    }

    public function getFileActionsModal($id = null)
    {
        return new FileActionsModal($id);
    }
}
