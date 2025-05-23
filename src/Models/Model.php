<?php

namespace Condoedge\Utils\Models;

use Condoedge\Utils\Models\ModelBase;
use Condoedge\Utils\Models\Traits\HasAddedModifiedByTrait;

class Model extends ModelBase
{
    use \Illuminate\Database\Eloquent\SoftDeletes;
    use HasAddedModifiedByTrait;
}