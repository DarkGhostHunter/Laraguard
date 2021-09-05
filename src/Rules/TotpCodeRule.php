<?php

namespace DarkGhostHunter\Laraguard\Rules;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Translation\Translator;
use DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable;

class TotpCodeRule
{
    /**
     * Create a new "totp code" rule instance.
     *
     * @param  \Illuminate\Contracts\Translation\Translator  $translator
     * @param  \Illuminate\Contracts\Auth\Authenticatable|null  $user
     */
    public function __construct(protected Translator $translator, protected ?Authenticatable $user = null)
    {
        //
    }

    /**
     * Validate that an attribute is a valid Two-Factor Authentication TOTP code.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function validate($attribute, $value): bool
    {
        return is_string($value)
            && $this->user instanceof TwoFactorAuthenticatable
            && $this->user->validateTwoFactorCode($value);
    }
}
