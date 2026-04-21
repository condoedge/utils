<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::post('edit-place-fields', \Condoedge\Utils\Kompo\ContactInfo\Maps\AddressPlaceEditingForm::class)->name('edit-place-fields');
});

Route::get('api/tutorials/{name}', function (string $name) {
    $path = resource_path("tutorials/{$name}.json");

    if (!file_exists($path)) {
        return response()->json(['error' => 'Tutorial not found'], 404);
    }

    $data = json_decode(file_get_contents($path), true);

    // Translate step html keys
    if (!empty($data['steps'])) {
        foreach ($data['steps'] as &$step) {
            if (!empty($step['html']) && trans()->has($step['html'])) {
                $step['html'] = __($step['html']);
            }
        }
    }

    return response()->json($data);
})->name('api.tutorials');

Route::post('_execute-lazy', [\Condoedge\Utils\Http\Controllers\LazyComponentController::class, 'execute'])
    ->name('utils.execute-lazy');

Route::post('_execute-lazy-batch', [\Condoedge\Utils\Http\Controllers\LazyComponentController::class, 'executeBatch'])
    ->name('utils.execute-lazy-batch');

if (config('kompo-utils.lazy_hierarchy.enabled')) {
    Route::get('_lazy-hierarchy/bootstrap', [\Condoedge\Utils\Http\Controllers\LazyHierarchyController::class, 'bootstrap'])
        ->name('utils.lazy-hierarchy.bootstrap');

    Route::get('_lazy-hierarchy/nodes', [\Condoedge\Utils\Http\Controllers\LazyHierarchyController::class, 'children'])
        ->name('utils.lazy-hierarchy.nodes');
}

Route::get('api/tutorials/{name}', function (string $name) {
    $processor = new \Condoedge\Utils\Tutorials\TutorialProcessor();
    return response()->json($processor->process($name));
})->middleware('auth')->name('tutorial.data');
