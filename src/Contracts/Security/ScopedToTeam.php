<?php

namespace Condoedge\Utils\Contracts\Security;

use Illuminate\Database\Eloquent\Builder;

/**
 * The model is filterable by team. Replaces the `$restrictByTeam` flag,
 * `$TEAM_ID_COLUMN`, `team_id` auto-detect, `team()` relation auto-detect,
 * `scopeSecurityForTeams`, `scopeSecurityForTeamByQuery`, and
 * `securityRelatedTeamIds()`.
 *
 * Lives in utils so models that don't depend on kompo-auth (e.g. utils'
 * Phone/Email/Address/File) can implement it. The kompo-auth subclass
 * `Kompo\Auth\Contracts\Security\HasOwnedRecords` extends this one for
 * back-compat with auth-side consumers (resolver, registry, etc.).
 */
interface ScopedToTeam
{
    /**
     * Apply the team filter to a read query. The implementer picks the shape
     * (whereIn / whereHas / subquery / column).
     *
     * @param array<int> $teamIds Team IDs the viewer is allowed to see.
     */
    public function applyTeamSecurityScope(Builder $query, array $teamIds): void;

    /**
     * Team IDs this instance belongs to. Used by write/delete checks and by
     * `TeamSecurityService::getTeamOwnersIdsSafe`.
     *
     * @return array<int>
     */
    public function getRelatedTeamIds(): array;
}
