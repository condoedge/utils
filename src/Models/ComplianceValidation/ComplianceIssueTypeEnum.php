<?php

namespace Condoedge\Utils\Models\ComplianceValidation;

enum ComplianceIssueTypeEnum: int
{
    case WARNING = 1;
    case ERROR = 2;
}