<?php

return [
    'load-migrations' => true,

    'translator-email' => env('TRANSLATOR_EMAIL', 'benoit@decizif.com'),
    
    'note-model-namespace' => \Condoedge\Utils\Models\Notes\Note::class,
    'file-model-namespace' => \Condoedge\Utils\Models\Files\File::class,

    'team-model-namespace' => getAppClass(config('kompo-auth.team-model-namespace'), App\Models\Teams\Team::class),

    'morphables-contact-associated-to-user' => [
        'person',
    ],

    'compliance-validation-rules' => [],

    // Enable plugin-based interception of Eloquent relationship methods.
    // When false, relationship overrides are completely disabled (zero overhead).
    // When true, plugins implementing interceptRelation() can modify relationship queries.
    'intercept-relations' => false,

    'lazy_hierarchy' => [
        'enabled' => false,
    ],

    'enable-compliance-command-execution' => true,
];