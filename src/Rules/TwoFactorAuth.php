<?php

namespace DarkGhostHunter\Laraguard\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class TwoFactorAuth implements Rule
{
    /**
     * The name of the rule.
     *
     * @var string
     */
    protected $rule = 'two_factor_auth';

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value) : bool
    {
        if (is_null($value)) {
            return false;
        }

        return Auth::user()->confirmTwoFactorAuth($value);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message() : string
    {
        return __('laraguard::validation.two_factor_auth');
    }

    /**
     * Convert the rule to a validation string.
     *
     * @return string
     */
    public function __toString() : string
    {
        return "{$this->rule}";
    }
}
