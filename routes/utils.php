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