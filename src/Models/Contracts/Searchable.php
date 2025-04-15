<?php

namespace Condoedge\Utils\Models\Contracts;

interface Searchable
{
    public function scopeSearch($query, $search);

    public function searchElement($result, $search);
}
