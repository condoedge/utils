<?php

namespace Condoedge\Utils\Models\Notes;

use Condoedge\Utils\Contracts\Security\HasOwnedRecords;
use Condoedge\Utils\Contracts\Security\ScopedToTeam;
use Condoedge\Utils\Models\Concerns\Security\BelongsToOneTeam;
use Condoedge\Utils\Models\Model;

class Note extends Model implements ScopedToTeam, HasOwnedRecords
{
    use BelongsToOneTeam;
    use \Condoedge\Utils\Models\Traits\BelongsToTeamTrait;

    protected $casts = [
        'date_nt' => 'datetime',
    ];

    /* RELATIONSHIPS */
    public function notable()
    {
        return $this->morphTo();
    }

    public function ownedRecordIdsForUser(int $userId): array
    {
        return $this->where('added_by', $userId)->pluck('id')->toArray();
    }

    /* SCOPES */
    public function scopeForNotable($query, $notable)
    {
        return $query->where('notable_type', get_class($notable))->where('notable_id', $notable->id);
    }

    public function scopeForNotableType($query, $notableType)
    {
        return $query->where('notable_type', $notableType);
    }

    public function scopeForNotableId($query, $notableId)
    {
        return $query->where('notable_id', $notableId);
    }

	public function scopeForTeam($query, $team = null)
    {
        return $query->where('team_id', $team?->id ?? safeCurrentTeamId());
    }

    /* ACTIONS */
    public function save(array $options = [])
    {
        $this->team_id = safeCurrentTeamId();

        return parent::save($options);
    }

    public function deletable()
    {
        return $this->added_by == auth()->id() || safeIsSuperAdmin();
    }
}