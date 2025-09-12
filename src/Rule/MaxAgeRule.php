<?php

namespace Condoedge\Utils\Rule;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;

class MaxAgeRule implements ValidationRule
{
    protected $maxAge;

    public function __construct($maxAge)
    {
        $this->maxAge = $maxAge;
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
        if (Carbon::parse($value)->age > $this->maxAge) {
            $fail(__('error.must-be-at-most') . ' ' . $this->maxAge . ' ' . __('error.years-old'));
        }
    }
}
