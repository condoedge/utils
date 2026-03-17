<?php

use Illuminate\Support\Facades\Route;

Route::post('forget-intro-component', function () {
    $introKey = request('intro_key');

    if ($introKey && auth()->check()) {
        auth()->user()->saveSetting($introKey, false);
    }

    return response()->json(['status' => 'success']);
})->name('forget-intro-component');

Route::middleware(['auth'])->group(function () {
    Route::post('edit-place-fields', \Condoedge\Utils\Kompo\ContactInfo\Maps\AddressPlaceEditingForm::class)->name('edit-place-fields');
});

Route::post('_execute-lazy', [\Condoedge\Utils\Http\Controllers\LazyComponentController::class, 'execute'])
    ->name('utils.execute-lazy');

Route::post('_execute-lazy-batch', [\Condoedge\Utils\Http\Controllers\LazyComponentController::class, 'executeBatch'])
    ->name('utils.execute-lazy-batch');