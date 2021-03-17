<?php

namespace DarkGhostHunter\Laraguard\Listeners;

use DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable;

trait ChecksTwoFactorCode
{
    /**
     * Returns if the login attempt should enforce Two Factor Authentication.
     *
     * @param  null|\DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable|\Illuminate\Contracts\Auth\Authenticatable  $user
     * @return bool
     */
    protected function shouldUseTwoFactorAuth($user = null)
    {
        if (! $user instanceof TwoFactorAuthenticatable) {
            return false;
        }

        $shouldUse = $user->hasTwoFactorEnabled();

        // If safe devices is active, then it should be used if the current is not.
        if ($this->isSafeDevicesEnabled()) {
            return $shouldUse && ! $user->isSafeDevice($this->request);
        }

        return $shouldUse;
    }

    /**
     * Checks if the app config has Safe Devices enabled.
     *
     * @return bool
     */
    protected function isSafeDevicesEnabled()
    {
        return $this->config['laraguard.safe_devices.enabled'];
    }


    /**
     * Checks if the user wants to add this device as "safe".
     *
     * @return bool
     */
    protected function wantsAddSafeDevice()
    {
        return $this->request->filled('safe_device');
    }

    /**
     * Returns if the Request has the Two Factor Code.
     *
     * @return bool
     */
    protected function hasCode()
    {
        return $this->request->filled($this->input);
    }

    /**
     * Checks if the Request has a Two Factor Code and is correct and valid.
     *
     * @param  \DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable  $user
     * @return bool
     */
    protected function hasValidCode(TwoFactorAuthenticatable $user)
    {
        return $this->hasCorrectCode() && $user->validateTwoFactorCode($this->request->input($this->input));
    }

    /**
     * Checks if the Request has a Two Factor Code and is correct.
     *
     * @return bool
     */
    protected function hasCorrectCode() {
        return ! validator($this->request->only($this->input), [$this->input => 'alphanum'])->fails();
    }
}
