<?php

namespace DarkGhostHunter\Laraguard\Events;

use DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable;

class TwoFactorRecoveryCodesGenerated
{
    /**
     * Create a new event instance.
     *
     * @param  \DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable  $user
     * @return void
     */
    public function __construct(public readonly TwoFactorAuthenticatable $user)
    {
        //
    }
}
