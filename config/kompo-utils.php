<?php

return [
    'load-migrations' => true,

    'translator-email' => env('TRANSLATOR_EMAIL', 'benoit@decizif.com'),

    // Country phone inputs start on, and whether the browser may ask a third party where the
    // visitor is. With detection on, the IP wins and this is only the fallback.
    'default-country-phone' => env('PHONE_DEFAULT_COUNTRY', 'CA'),
    'detect-country-phone-by-ip' => env('PHONE_DETECT_COUNTRY_BY_IP', false),

    'note-model-namespace' => \Condoedge\Utils\Models\Notes\Note::class,
    'file-model-namespace' => \Condoedge\Utils\Models\Files\File::class,

    'team-model-namespace' => getAppClass(config('kompo-auth.team-model-namespace'), App\Models\Teams\Team::class),

    'morphables-contact-associated-to-user' => [
        'person',
    ],

    'compliance-validation-rules' => [],

    // Re-announce every still-open compliance issue on each rule run, not only newly detected ones.
    'compliance-remind-open-issues' => false,

    // Enable plugin-based interception of Eloquent relationship methods.
    // When false, relationship overrides are completely disabled (zero overhead).
    // When true, plugins implementing interceptRelation() can modify relationship queries.
    'intercept-relations' => false,

    'lazy_hierarchy' => [
        'enabled' => false,
    ],

    // Kill switch for Condoedge\Utils\Services\CachedServiceWrapper.
    // When false, the wrapper passes through to the underlying service without
    // touching the Cache facade — so tests and local dev see fresh results.
    'cache_wrapping_enabled' => env('CACHE_WRAPPING_ENABLED', false),
];