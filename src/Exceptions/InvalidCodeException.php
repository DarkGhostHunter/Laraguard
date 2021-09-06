<?php

namespace DarkGhostHunter\Laraguard\Exceptions;

use DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable;
use Illuminate\Validation\ValidationException;

class InvalidCodeException extends ValidationException
{
    /**
     * Create a new exception instance.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @param  \Symfony\Component\HttpFoundation\Response|null  $response
     * @param  string  $errorBag
     * @return void
     */
    public function __construct($validator, $response = null, $errorBag = 'default')
    {
        parent::__construct($validator, $response, $errorBag);

        $this->withMessage(trans('laraguard:validation.totp_code'));
    }

    /**
     * Sets a custom validation message.
     *
     * @param  string  $message
     *
     * @return $this
     */
    public function withMessage(string $message): static
    {
        $this->validator->errors()->add(config('laraguard.input', '2fa_code'), $message);

        return $this;
    }
}
