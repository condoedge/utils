<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::post('edit-place-fields', \Condoedge\Utils\Kompo\ContactInfo\Maps\AddressPlaceEditingForm::class)->name('edit-place-fields');
});

Route::post('_execute-lazy', [\Condoedge\Utils\Http\Controllers\LazyComponentController::class, 'execute'])
    ->name('utils.execute-lazy');

Route::post('_execute-lazy-batch', [\Condoedge\Utils\Http\Controllers\LazyComponentController::class, 'executeBatch'])
    ->name('utils.execute-lazy-batch');

Route::get('api/tutorials/{name}', function (string $name) {
    $processor = new \Condoedge\Utils\Tutorials\TutorialProcessor();
    return response()->json($processor->process($name));
})->middleware('auth')->name('tutorial.data');

Route::get('tutorial-step-builder/{tutorialName?}', \Condoedge\Utils\Tutorials\TutorialStepBuilder::class)
    ->middleware('auth')
    ->name('tutorial.step-builder');