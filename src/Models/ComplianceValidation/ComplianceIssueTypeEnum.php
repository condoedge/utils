<?php

namespace Condoedge\Utils\Models\ComplianceValidation;

enum ComplianceIssueTypeEnum: int
{
    use \Kompo\Models\Traits\EnumKompo;
    
    case WARNING = 1;
    case ERROR = 2;

    public function label(): string
    {
        // Assuming ComplianceIssueTypeEnum has these values
        return match($this) {
            self::WARNING => __('compliance.warning'),
            self::ERROR => __('compliance.error'),
        };
    }

    public function classes()
    {
        return match($this) {
            self::WARNING => 'bg-warning text-white',
            self::ERROR => 'bg-danger text-white',
        };
    }
}