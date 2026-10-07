<?php

namespace Condoedge\Utils\Models;

use Condoedge\Utils\Models\ModelBase;
use Condoedge\Utils\Models\Traits\HasAddedModifiedByTrait;
use Condoedge\Utils\Models\Traits\HasDeletedByTrait;

class Model extends ModelBase
{
    use \Illuminate\Database\Eloquent\SoftDeletes;
    use HasAddedModifiedByTrait;
    use HasDeletedByTrait;
}