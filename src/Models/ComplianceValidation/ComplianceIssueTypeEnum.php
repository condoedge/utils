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
            self::WARNING => __('translate.warning'),
            self::ERROR => __('translate.error'),
        };
    }

    public function classes()
    {
        return match($this) {
            self::WARNING => 'bg-yellow-100 text-yellow-800',
            self::ERROR => 'bg-red-100 text-red-800',
        };
    }
}