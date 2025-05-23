<?php

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