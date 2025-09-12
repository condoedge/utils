<?php

namespace Condoedge\Utils\Rule;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NameRule implements ValidationRule
{
    protected $maxLength = 100;

    public function __construct(int $maxLength = 100)
    {
        if ($maxLength) {
            $this->maxLength = $maxLength;
        }
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            $fail(__('translate.required', ['attribute' => $attribute]));
        }

        if (mb_strlen($value) < 2 || mb_strlen($value) > $this->maxLength) {
            $fail(__('translate.invalid_length', ['attribute' => $attribute]));
        }

        if (!preg_match('/^[A-Za-z ]{2,'.$this->maxLength.'}$/', $value)) {
            $fail(__('translate.invalid_format', ['attribute' => $attribute]));
        }

        if (preg_match('/\b(\w+) \1\b/', $value)) {
            $fail(__('translate.no_repeated_names', ['attribute' => $attribute]));
        }

        if (preg_match('/^(.)\1{2,}$/', $value)) {
            $fail(__('translate.no_repeated_characters', ['attribute' => $attribute]));
        }
    }
}
