<?php

return [
    'types' => [
        'general' => ['general.general', ''],
    ],

    'image-compression' => [
        'target-max-kb' => env('IMAGE_COMPRESSION_TARGET_KB', 2048),
        'incoming-max-kb' => env('IMAGE_COMPRESSION_INCOMING_KB', 20480),
        'max-width' => 2000,
        'max-widths' => [
            'logo' => 200,
            'cover' => 1600,
        ],
        'max-megapixels' => 60,
        'quality-ladder' => [85, 75, 65, 55, 45],
    ],

    'file-model-namespace' =>  \Condoedge\Utils\Models\Files\File::class,
];