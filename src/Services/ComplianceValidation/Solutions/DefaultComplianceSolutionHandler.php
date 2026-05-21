<?php

namespace Condoedge\Utils\Services\ComplianceValidation\Solutions;

use Kompo\Elements\BaseElement;

/**
 * Default handler: delegates to the rule's individualValidationDetailsComponent.
 * Used by any rule that doesn't override BaseRule::getSolutionHandler().
 */
class DefaultComplianceSolutionHandler extends AbstractComplianceSolutionHandler
{
    public function getComponent(): ?BaseElement
    {
        return $this->rule?->individualValidationDetailsComponent($this->issue);
    }
}
