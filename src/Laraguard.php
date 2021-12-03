<?php

namespace DarkGhostHunter\Laraguard;

use Closure;
use DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

use function app;
use function trans;
use function validator;

class Laraguard
{
    /**
     * The default name to check for the 2FA code.
     *
     * @var string
     */
    public const INPUT = '2fa_code';

    /**
     * The default name to check for safe-device registration.
     *
     * @var string
     */
    public const SAFE_DEVICE_INPUT = 'safe_device';


    /**
     * Check if the user uses TOTP and has a valid code.
     *
     * @param  string  $input
     * @param  string  $safeDeviceInput
     * @return \Closure
     */
    public static function hasCode(
        string $input = self::INPUT,
        string $safeDeviceInput = self::SAFE_DEVICE_INPUT
    ): Closure
    {
        return static function ($user) use ($input, $safeDeviceInput): bool {
            return (new static(app('config'), app('request'), $input, $safeDeviceInput))->validate($user);
        };
    }

    /**
     * Check if the user uses TOTP and has a valid code.
     *
     * @param  string  $input
     * @param  string  $safeDeviceInput
     * @param  string|null  $message
     * @return \Closure
     */
    public static function hasCodeOrFails(
        string $input = self::INPUT,
        string $safeDeviceInput = self::SAFE_DEVICE_INPUT,
        string $message = null,
    ): Closure
    {
        return static function ($user) use ($input, $safeDeviceInput, $message): bool {
            if ((static::hasCode($input, $safeDeviceInput))($user)) {
                return true;
            }

            throw Exceptions\InvalidCodeException::withMessages([
                $input => value($message, $user) ?? trans('laraguard::validation.totp_code'),
            ]);
        };
    }

    /**
     * Creates a new Laraguard instance.
     *
     * @param  \Illuminate\Contracts\Config\Repository  $config
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $input
     * @param  string  $safeDeviceInput
     */
    public function __construct(
        protected readonly Repository $config,
        protected readonly Request $request,
        protected readonly string $input,
        protected readonly string $safeDeviceInput,
    )
    {
        //
    }

    /**
     * Check if the user uses TOTP and has a valid code.
     *
     * If the user does not use TOTP, no checks will be done.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @return bool
     */
    public function validate(Authenticatable $user): bool
    {
        // If the user does not use 2FA or is not enabled, don't check.
        if (! $user instanceof TwoFactorAuthenticatable || ! $user->hasTwoFactorEnabled()) {
            return true;
        }

        // If safe devices are enabled, and this is a safe device, bypass.
        if ($this->isSafeDevicesEnabled() && $user->isSafeDevice($this->request)) {
            return true;
        }

        // If the code is valid, return true after it tries to save the device.
        if ($this->requestHasCode() && $user->validateTwoFactorCode($this->getCode())) {
            if ($this->isSafeDevicesEnabled() && $this->wantsToAddDevice()) {
                $user->addSafeDevice($this->request);
            }

            return true;
        }

        return false;
    }

    /**
     * Checks if the app config has Safe Devices enabled.
     *
     * @return bool
     */
    protected function isSafeDevicesEnabled(): bool
    {
        return $this->config->get('laraguard.safe_devices.enabled', false);
    }

    /**
     * Checks if the Request has a Two-Factor Code and is valid.
     *
     * @return bool
     */
    protected function requestHasCode(): bool
    {
        return !validator($this->request->only($this->input), [
            $this->input => 'required|alpha_num',
        ])->fails();
    }

    /**
     * Returns the code from the request input.
     *
     * @return string
     */
    protected function getCode(): string
    {
        return $this->request->input($this->input);
    }

    /**
     * Checks if the user wants to add this device as "safe".
     *
     * @return bool
     */
    protected function wantsToAddDevice(): bool
    {
        return $this->request->filled($this->safeDeviceInput);
    }
}
