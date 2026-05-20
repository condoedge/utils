<?php

namespace Condoedge\Utils\Kompo\ComplianceValidation;

use Condoedge\Utils\Facades\TeamModel;

class TeamComplianceIssuesTable extends AbstractComplianceIssuesTable
{
    public function query()
    {
        $teamsIds = $this->getTeamsIds();

        return $this->baseQuery()
            ->where(fn($q) => $q->forTeam($teamsIds)
            ->orWhereHas('validatable', function ($q) use ($teamsIds) {
                $q->alreadyVerifiedAccess();
                
                $teamClass = TeamModel::getClass();

                if ($q->getModel() instanceof $teamClass) {
                    $q->whereIn('id', $teamsIds);
                    return;
                }

                if (method_exists($q->getModel(), 'scopeForTeams')) {
                    $q->forTeams($teamsIds);
                }
            }));
    }

    protected function getTeamsIds()
    {
        return safeGetAllTeamChildrenIds();
    }

    protected function defaultValidatableType(): ?string
    {
        return \Condoedge\Utils\Facades\TeamModel::getClass();
    }
}