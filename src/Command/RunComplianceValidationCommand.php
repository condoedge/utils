<?php

namespace Condoedge\Utils\Command;

use Condoedge\Utils\Services\ComplianceValidation\ValidationService;
use Illuminate\Console\Command;

class RunComplianceValidationCommand extends Command
{
    protected $signature = 'compliance:run-validation';

    protected $description = 'Run compliance validation rules';

    public function handle()
    {
        $rules = config('kompo-utils.compliance-validation-rules', []);

        $validationService = app(ValidationService::class);
        $validationService->validate($rules);
    }
}