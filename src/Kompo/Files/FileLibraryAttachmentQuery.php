<?php

namespace Condoedge\Utils\Kompo\Files;

use Condoedge\Utils\Facades\FileModel;
use Condoedge\Utils\Kompo\Common\Query;

class FileLibraryAttachmentQuery extends Query
{
    public $layout = 'Table';
	public $activeClass = 'ring-2 ring-level3 border-level3 rounded-xl';
    public $isWhiteTable = true;
    public $paginationType = 'Scroll';
    public $itemsWrapperClass = 'p-4 mb-8 overflow-y-auto mini-scroll w-max-4xl';
    public $itemsWrapperStyle = 'max-height: 300px';

	public $checkedItemIds;

	public function created()
	{
		$this->checkedItemIds = json_decode($this->parameter('checked_items'), true);

		$this->itemsWrapperClass = $this->itemsWrapperClass.' mx-4 px-6 pb-8';
	}

	public function query()
	{
		return FileModel::getLibrary([
            'filename' => request('name'),
            'fileable_type' => request('parent_type_bis'),
            'year' => request('year'),
            'month' => request('month'),
        ]);
	}

	public function top()
	{
		return _Rows(
			_FlexBetween(

				_Title('files-attach-from-library')->class('text-2xl sm:text-3xl font-bold')
					->icon('document-text')
					->class('font-semibold mb-4 md:mb-0')
					->class('flex items-center'),

				_FlexEnd(
					_Button('files-confirm')->getElements('confirmSelection')->inPanel('linked-attachments')
						->closeModal()
		                ->config(['withCheckedItemIds' => true])
				)->class('flex-row-reverse md:flex-row md:ml-8')
			)
			->class('bg-gray-50 border-b border-gray-200 px-4 py-6 sm:px-6 rounded-t-xl')
			->class('flex-col items-start md:flex-row md:items-center')
			->alignStart(),
			_Rows(
				FileModel::fileFilters(
					_MiniTitle('files-link-from-library'),
				)
			)->class('px-6 py-4'),
		);
	}

    public function headers()
    {
        return [
            _Th('files.date'),
            _Th('files.file'),
        ];
    }

	public function render($file)
	{
		return _TableRow(
            _HtmlDate($file->created_at),
            _Html($file->name)->class('word-break-all'),
        )->emit('checkItemId', ['id' => $file->id])->class('cursor-pointer');
	}

	public function confirmSelection($selectedIds)
	{
		return static::selectedFiles($selectedIds);
	}

	public static function selectedFiles($selectedIds = [])
	{
		$selectedFiles = FileModel::whereIn('id', $selectedIds ?: [])->get();

		return _Rows(
			!$selectedFiles->count() ? null : _MultiSelect()->name('selected_files', false)
				->options($selectedFiles->mapWithKeys(fn($file) => [$file->id => $file->name]))
				->value($selectedFiles->pluck('id'))
				->class('mb-0'),
			_Button('files-add-from-library')
				->class('text-sm vlBtn vlBtnOutlined')->icon('icon-plus')
				->get('file-add-attachment.modal', [
					'checked_items' => json_encode($selectedIds),
				])->inModal(),
		);
	}

	public static function libraryFilesPanel($selectedIds = [])
	{
		return _Panel(
            static::selectedFiles($selectedIds)
        )->id('linked-attachments');
	}

	public function getYearsMonthsFilter()
	{
		return FileModel::yearlyMonthlyLinkGroup();
	}
}
