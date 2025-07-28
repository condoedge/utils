<?php

namespace Condoedge\Utils\Rule;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class FileMimeTypeRule implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {

    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (is_string($value)) {
            return true;
        }

        $validator = Validator::make(
            ['file' => $value], // The data to validate (the file itself)
            ['file' => 'mimes:' . implode(',', attachmentsValidTypes())] // The rule to apply
        );

        return $validator->passes();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('utils.the-file-must-be-one-of-these-types').' '.implode(',', attachmentsValidTypes());
    }
}
