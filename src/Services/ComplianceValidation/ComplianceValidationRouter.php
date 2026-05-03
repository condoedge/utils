<?php

namespace Condoedge\Utils\Services\ComplianceValidation;

use Illuminate\Support\Facades\Route;

class ComplianceValidationRouter
{
    public function setRoutes()
    {
        Route::get('compliances-issues', \Condoedge\Utils\Kompo\ComplianceValidation\TeamComplianceIssuesTable::class)->name('compliances-issues.list');

        Route::get('compliance-issue-overview/{id}', \Condoedge\Utils\Kompo\ComplianceValidation\ComplianceIssueOverviewPage::class)
            ->name('compliance-issue.overview');
    }
}