<?php

namespace Condoedge\Utils\Rule;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class InternationalPhoneRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Basic international phone number pattern
        $pattern = '/^\+?[1-9]\d{7,20}$/';

        if (!preg_match($pattern, $value)) {
            $fail(__('validation.invalid-phone-format', ['attribute' => $attribute]));
        }
    }
}
