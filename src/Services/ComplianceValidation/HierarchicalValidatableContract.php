<?php

namespace Condoedge\Utils\Services\ComplianceValidation;

interface HierarchicalValidatableContract
{
    /**
     * Ordered breadcrumb segments from outermost ancestor to the validatable itself.
     * @return string[]
     */
    public function validatableBreadcrumb(): array;
}
