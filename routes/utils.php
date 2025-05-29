<?php

use Illuminate\Support\Facades\Route;

Route::post('forget-intro-component', function () {
    $introKey = request('intro_key');

    if ($introKey && auth()->check()) {
        auth()->user()->saveSetting($introKey, false);
    }

    return response()->json(['status' => 'success']);
})->name('forget-intro-component');