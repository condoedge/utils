<?php

namespace Condoedge\Utils\Kompo\ComplianceValidation;

use Condoedge\Utils\Facades\TeamModel;

class TeamComplianceIssuesTable extends AbstractComplianceIssuesTable
{
    public function top()
    {
        return _Rows(
            _Rows(parent::top()),

            _FlexEnd(
                _Toggle('compliance.include-related-entities')
                    ->name('include_related_entities', false)
                    ->filter(),
            ),
        );
    }

    public function query()
    {
        $teamsIds = $this->getTeamsIds();

        return $this->baseQuery()
            ->where(fn($q) => $q->forTeam($teamsIds)
                ->when(request('include_related_entities'), fn($q) => $q
                ->orWhereHas('validatable', function ($q) use ($teamsIds) {
                    $teamClass = TeamModel::getClass();

                    if ($q->getModel() instanceof $teamClass) {
                        $q->whereIn('id', $teamsIds);
                        return;
                    }

                    if (method_exists($q->getModel(), 'scopeForTeams')) {
                        $q->forTeams($teamsIds);
                    }
                })));
    }

    protected function getTeamsIds()
    {
        return safeGetAllTeamChildrenIds();
    }
}