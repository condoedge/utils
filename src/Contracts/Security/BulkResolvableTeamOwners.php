<?php

namespace Condoedge\Utils\Contracts\Security;

use Illuminate\Database\Eloquent\Model;

/**
 * Optional companion to ScopedToTeam: lets a model class resolve the
 * related team IDs for many same-class instances in a single query instead
 * of N round-trips. Used by `BatchPermissionService` to pre-warm the
 * per-request team-owner cache before the per-instance security loop.
 *
 * Implementers SHOULD also implement ScopedToTeam — `getRelatedTeamIds()`
 * remains the per-instance source of truth; this contract just provides
 * a bulk path for collections.
 *
 * Lives in utils so any team-scoped model (utils, crm, app-level) can
 * opt in without depending on kompo-auth internals.
 */
interface BulkResolvableTeamOwners
{
    /**
     * Resolve related team_ids for the given same-class instances in one
     * round-trip. The caller guarantees all `$models` are instances of the
     * implementing class (callers MUST group by class first).
     *
     * @param  iterable<Model> $models
     * @return array<int|string, array<int>>  Map of primary-key => team_ids.
     *                                        Missing keys are treated as "no
     *                                        teams" by the caller.
     */
    public static function bulkResolveRelatedTeamIds(iterable $models): array;
}
