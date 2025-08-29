<?php

namespace Condoedge\Utils\Models\Traits;

use Condoedge\Utils\Facades\TeamModel;

trait BelongsToTeamTrait
{
    /* RELATIONS */
    public function team()
    {
        return $this->belongsTo(TeamModel::getClass());
    }

    /* CALCULATED FIELDS */
    public function getTeamName()
    {
        return $this->team->team_name;
    }

    /* ACTIONS */
    public function setTeamId($teamId = null)
    {
        $this->team_id = $teamId ?: safeCurrentTeamId();
    }

    /* SCOPES */
    public function scopeForTeam($query, $teamIdOrIds = null)
    {
        scopeWhereBelongsTo($query, 'team_id', $teamIdOrIds, safeCurrentTeamId());
    }

    public function deletable()
    {
        return safeIsSuperAdmin() || $this->team_id == safesafeCurrentTeamId();
    }

}
