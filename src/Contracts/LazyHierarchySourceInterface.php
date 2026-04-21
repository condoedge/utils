<?php

namespace Condoedge\Utils\Contracts;

use Illuminate\Http\Request;

interface LazyHierarchySourceInterface
{
    /**
     * Return the initial visible tree slice.
     */
    public function bootstrap(Request $request): array;

    /**
     * Return a lazy-loaded child slice for one parent node.
     */
    public function children(Request $request): array;
}
