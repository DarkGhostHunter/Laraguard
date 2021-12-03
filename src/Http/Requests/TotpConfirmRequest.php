<?php

namespace DarkGhostHunter\Laraguard\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

use function now;

/**
 * @method \DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable user($guard = null)
 */
class TotpConfirmRequest extends FormRequest
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
            static::$input => 'required|alpha_num|totp_code'
        ];
    }

    /**
     * Handle a passed validation attempt.
     *
     * @return void
     */
    protected function passedValidation(): void
    {
        $this->session()->put(config('laraguard.confirm.key', '_2fa.totp_confirmed_at'), now()->timestamp);
    }
}
