<?php

namespace Condoedge\Utils\Services;

use Condoedge\Utils\Kompo\Files\FileLibraryAttachmentQuery;
use Condoedge\Utils\Kompo\Files\FileLibraryAttachmentTable;
use Condoedge\Utils\Kompo\Files\FilesManagerView;
use Illuminate\Support\Facades\Route;

class FileService
{
    public static function setAttachmentRoutes()
    {
        Route::get('add-file-as-attachment/{checked_items?}', FileLibraryAttachmentQuery::class)->name('file-add-attachment.modal');
        Route::get('add-file-as-attachment-table/{checked_items?}', FileLibraryAttachmentTable::class)->name('file-add-attachment.modal-with-table');
    }

    public static function setUploadManagerRoutes()
    {
        Route::get('files-manager', FilesManagerView::class)->name('files-manager');
    }

    public static function setAllRoutes()
    {
        self::setAttachmentRoutes();
        self::setUploadManagerRoutes();
    }
}
