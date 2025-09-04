<?php

namespace Condoedge\Utils\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class FileColumnsController extends Controller
{
    public function download($type, $id, $column, $index = null)
    {
        $model = getModelFromMorphable($type, $id);

        $isArrayOfImages = (bool) $index;

        if ($isArrayOfImages) {
            $file = $model->getAttribute($column)[$index] ?? null;
        } else {
            $file = $model->getAttribute($column);
        }

        if (!$file || !isset($file['path'])) {
            return response()->json(['error' => 'File not found'], 404);
        }

        return response()->download(
            Storage::disk($file['disk'] ?? 'local')->get($file['path']),
        );
    }

    public function display($type, $id, $column, $index = null)
    {
        $model = getModelFromMorphable($type, $id);

        $isArrayOfImages = $index !== null;

        if ($isArrayOfImages) {
            $file = $model->getAttribute($column)[$index] ?? null;
        } else {
            $file = $model->getAttribute($column);
        }

        if (!$file || !isset($file['path'])) {
            return response()->json(['error' => 'File not found'], 404);
        }

        $disk = $file['disk'] ?? 'public';
        $path = $file['path'];

        $file = Storage::disk($disk)->get($path);
        $type = Storage::disk($disk)->mimeType($path);

        $response = \Response::make($file, 200);
        $response->header("Content-Type", $type);

        return $response;
    }
}
