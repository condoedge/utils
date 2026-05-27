<?php

namespace Condoedge\Utils\Models;

use Condoedge\Utils\Contracts\Security\HasOwnedRecords;
use Condoedge\Utils\Models\Model;
use Condoedge\Utils\Models\Traits\BelongsToUserTrait;

class UserSetting extends Model implements HasOwnedRecords
{
	use BelongsToUserTrait;

	public function ownedRecordIdsForUser(int $userId): array
    {
        return $this->where('user_id', $userId)->pluck('id')->toArray();
    }
}
