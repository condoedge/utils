<?php

namespace Condoedge\Utils\Models;

use Condoedge\Utils\Models\Model;
use Condoedge\Utils\Models\Traits\BelongsToUserTrait;

class UserSetting extends Model
{
	use BelongsToUserTrait;
}
