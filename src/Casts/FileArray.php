<?php

namespace Condoedge\Utils\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class FileArray implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param array<string, mixed> $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        $returnValue = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $returnValue = null;
        }

        $finalValue = [];

        if (is_array($returnValue)) {
            foreach ($returnValue as $key => $file) {
                $file['html_el'] = '<span data-index="' . $key . '">' . $file['name'] . '</a>';
                $file['index'] = $key;

                $finalValue[] = $file;
            }
        }

        return $finalValue;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param array<string, mixed> $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        // Convertir el array en una cadena JSON, similar al casteo "array" de Laravel.
        return is_null($value) ? $value : json_encode($value);
    }
}
