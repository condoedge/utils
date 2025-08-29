<?php

namespace Condoedge\Utils\Models\ComplianceValidation;

enum ComplianceErrorTypesEnum: int
{
    case WARNING = 1;
    case ERROR = 2;
}