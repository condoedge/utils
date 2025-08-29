<?php

return [
    'note-model-namespace' => \Condoedge\Utils\Models\Notes\Note::class,
    'file-model-namespace' => \Condoedge\Utils\Models\Files\File::class,

    'team-model-namespace' => getAppClass(config('kompo-auth.team-model-namespace'), App\Models\Teams\Team::class),

    'morphables-contact-associated-to-user' => [
        'person',
    ],

    'compliance-validation-rules' => [],
];