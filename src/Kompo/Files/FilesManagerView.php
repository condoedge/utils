<?php

namespace Condoedge\Utils\Kompo\Files;

use Condoedge\Utils\Facades\FileModel;
use Condoedge\Utils\Kompo\Common\Table;

class FilesManagerView extends Table
{
	public $containerClass = 'container-fluid';

	public $id = 'file-manager-view';

    public $paginationType = 'Scroll';
    public $itemsWrapperClass = 'overflow-y-auto mini-scroll bg-white rounded-2xl p-4';
    public $itemsWrapperStyle = 'max-height: calc(100vh - 150px)';

    public $tableClass = 'table-fixed vlTableNoBorder';

	public function query()
	{
        $search = request('name');
        $type = request('parent_type_bis');
        $year = request('year');
        $month = request('month');

		return FileModel::getLibrary([
            'filename' => $search,
            'fileable_type' => $type,
            'year' => $year,
            'month' => $month,
        ]);
	}

	public function top()
	{
		return FileModel::fileFilters(
			_TitleMain('files-file-manager')->class('mb-4'),
			_Button('files-upload-files')->icon('upload')->class('md:hidden mb-4')
				->selfUpdate('getFileUploadModal')
				->inModal(),
		);
	}

	public function right()
	{
		return _Rows(
            _Button('files-upload-files')->icon('upload')->class('ml-0 sm:ml-4 mb-4 hidden md:flex')
            ->selfUpdate('getFileUploadModal')
            ->inModal(),
        	_Panel(
    			_TitleCard('files-file-infos'),
            	FileModel::emptyPanel(),
            )->id('file-info-panel')
            ->closable()
            ->class('dashboard-card p-4 mb-4 ml-0 sm:ml-4'), //width managed in CSS
        	_Rows(

	        )->id('recent-files-div')
	        ->class('dashboard-card p-4 mb-4 ml-0 sm:ml-4 w-1/4vw'),
		)->class('ml-0 hidden md:block file-manager-right');
	}

	public function headers()
	{
		return [
			_Th('general-name')->sort('name')->class('w-60'),
            _Th('general-type')->sort('type')->class('w-20'),
			_Th('general-date')->sort('created_at')->class('w-20'),
            _Th('general-actions')->sort('updated_at')->class('w-10'),
		];
	}

	public function render($file)
	{
		$fileable = $file->fileable;
		$canView = auth()->user()->can('viewFileOf', $fileable);

		return _TableRow(
			$this->renderMobileCard($file),
            _Html($file->name)->tdClass('desktop-only-cell'),
            _Html(ucfirst($file->fileable_type ?: '-'))->tdClass('desktop-only-cell'),
			$file->uploadedAt()->tdClass('desktop-only-cell'),
            _FlexBetween(
                _Link()->class('mt-1 -mr-2')->col('col-md-3')
                    ->icon('arrow-down')
                    ->href('files.download', ['id' => $file->id, 'type' => $file->getMorphClass()]),
                _Delete($file)->col('col-md-3'),
            )->class('px-2 gap-4')->tdClass('desktop-only-cell'),
		)->class('text-sm cursor-pointer')
		->selfGet('getFileInfo', [
			'id' => $file->id,
		])->inPanel('file-info-panel');
	}

	protected function renderMobileCard($file)
	{
		return _Rows(
			_FlexBetween(
				_Rows(
					_Html($file->name)->class('font-semibold text-sm truncate'),
					_Html(ucfirst($file->fileable_type ?: '-'))->class('text-xs text-gray-500 mt-0.5'),
				)->class('min-w-0 flex-1'),
				_Flex(
					_Link()->icon(_Sax('arrow-down', 18))
						->class('!bg-level4 !w-9 !h-9 !rounded-full !text-greenmain flex items-center justify-center')
						->href('files.download', ['id' => $file->id, 'type' => $file->getMorphClass()]),
					_Delete($file)->class('!bg-red-50 !w-9 !h-9 !rounded-full !text-danger flex items-center justify-center'),
				)->class('gap-2 flex-shrink-0'),
			)->class('items-center'),
			_Html($file->created_at->translatedFormat('d M Y'))->class('text-xs text-gray-400 mt-1'),
		)->class('p-3 bg-white rounded-xl shadow-sm border border-gray-100')
		->tdClass('mobile-only-cell');
	}

    public function getFileInfo()
    {
        $id = request('id');

    	return new FileInfo($id);
    }

	public function getYearsMonthsFilter()
	{
		return FileModel::yearlyMonthlyLinkGroup();
	}

	public function getFileUploadModal()
	{
		return new FileUploadModalManager();
	}
}
