<?php

use Condoedge\Utils\Kompo\HttpExceptions\GenericErrorView;
use Condoedge\Utils\Kompo\HttpExceptions\NotFoundView;

return [
    'error_view_map' => [
        404 => NotFoundView::class,
        'default' => GenericErrorView::class,
    ],
];