<?php

namespace Condoedge\Utils\Rule;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NameRule implements ValidationRule
{
    protected $maxLength = 100;
    protected $allowNumbers = false;
    protected $allowParentheses = false;

    public function __construct(int $maxLength = 100, bool $allowNumbers = false, bool $allowParentheses = false)
    {
        $this->maxLength = $maxLength;
        $this->allowNumbers = $allowNumbers;
        $this->allowParentheses = $allowParentheses;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            $fail(__('validation.required', ['attribute' => $attribute]));
        }

        if (mb_strlen($value) < 2 || mb_strlen($value) > $this->maxLength) {
            $fail(__('validation.invalid_length', ['attribute' => $attribute]));
        }

        if (!preg_match($this->buildValidCharsPattern(), $value)) {
            $fail(__('validation.invalid_format', ['attribute' => $attribute]));
        }

        if (preg_match('/\b(\w+) \1\b/', $value)) {
            $fail(__('validation.no_repeated_names', ['attribute' => $attribute]));
        }

        if (preg_match('/^(.)\1{2,}$/', $value)) {
            $fail(__('validation.no_repeated_characters', ['attribute' => $attribute]));
        }
    }

    protected function buildValidCharsPattern(): string
    {
        $pattern = '/^[\p{L}\s\.';
        if ($this->allowNumbers) {
            $pattern .= '0-9';
        }
        if ($this->allowParentheses) {
            $pattern .= '\(\)';
        }
        $pattern .= '\-\'`]{2,'.$this->maxLength.'}$/u';

        return $pattern;
    }
}
