<?php

use Condoedge\Utils\Http\Controllers\FileColumnsController;
use Condoedge\Utils\Kompo\Files\DisplayFileModal;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function(){
    // Files
	Route::get('files-display/{type}/{id}', \Condoedge\Utils\Http\Controllers\FilesDisplayController::class)->name('files.display');
	Route::get('files-download/{type}/{id}', \Condoedge\Utils\Http\Controllers\FilesDownloadController::class)->name('files.download');
	Route::get('image-preview/{type}/{id}', \Condoedge\Utils\Kompo\Files\ImagePreview::class)->name('image.preview');
	Route::get('pdf-preview/{type}/{id}', \Condoedge\Utils\Kompo\Files\PdfPreview::class)->name('pdf.preview');
	Route::get('audio-preview/{type}/{id}', \Condoedge\Utils\Kompo\Files\AudioPreview::class)->name('audio.preview');
	Route::get('video-preview/{type}/{id}', \Condoedge\Utils\Kompo\Files\VideoPreview::class)->name('video.preview');
});

Route::get('display-files-modal/{mime}/{type}/{id}/{column}/{index?}', DisplayFileModal::class)->name('preview-files-modal');

Route::get('display-files/{type}/{id}/{column}/{index?}', [FileColumnsController::class, 'display'])->name('preview-files');