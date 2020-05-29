<?php

namespace DarkGhostHunter\Laraguard\Rules;

use DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class TotpCode implements Rule
{
    /**
     * The name of the rule.
     *
     * @var string
     */
    protected $rule = 'totp_code';

    /**
     * The auth user.
     *
     * @var \Illuminate\Foundation\Auth\User
     */
    protected $user;

    /**
     * Create a new "totp code" rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->user = Auth::user();
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value) : bool
    {
        if ($this->user instanceof TwoFactorAuthenticatable) {
            return is_string($value) && $this->user->twoFactorAuth->validateCode($value);
        }
    
        return false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message() : string
    {
        return __('laraguard::validation.totp_code');
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
