<?php

namespace Condoedge\Utils\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * "Manual run" of every configured rule. One queued at a time, however many clicks, and never
 * retried: a full run outlasts the worker's 60s default and each retry started all rules over.
 */
class RunComplianceValidation implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, Queueable;

    public $timeout = 0;

    public $tries = 1;

    public function handle(): void
    {
        complianceService()->validateDefaultRules();
    }
}
