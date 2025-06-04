<?php

namespace Condoedge\Utils\Rule;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;

class MinAgeRule implements ValidationRule
{
    protected $minAge;

    public function __construct($minAge)
    {
        $this->minAge = $minAge;
    }

    /**
     * Run the validation rule.
     *
     * @param string $attribute
     * @param mixed $value
     * @param \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        if (Carbon::parse($value)->age < $this->minAge) {
            $fail(__('translate.error.must-be-at-least') . ' ' . $this->minAge . ' ' . __('translate.error.years-old'));
        }
    }
}
