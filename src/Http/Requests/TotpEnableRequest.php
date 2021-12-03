<?php

namespace DarkGhostHunter\Laraguard\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use LogicException;

/**
 * @method \DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable user($guard = null)
 */
class TotpEnableRequest extends FormRequest
{
    /**
     * The name of the input containing the code.
     *
     * @var string
     */
    public static string $input = '2fa_code';

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            static::$input => 'required|numeric'
        ];
    }

    /**
     * Handle a passed validation attempt.
     *
     * @return void
     */
    protected function passedValidation(): void
    {
        if (! $user = $this->user()) {
            throw new LogicException('There is no authenticated user for this request to enable 2FA.');
        }

        $user->confirmTwoFactorAuth($this->input(static::$input));
    }

    /**
     * Return the recovery codes generated for the authentication.
     *
     * @return \Illuminate\Support\Collection
     */
    public function recoveryCodes(): Collection
    {
        return $this->user()->getRecoveryCodes();
    }
}
