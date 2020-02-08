<?php

namespace Tests\Stubs;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use DarkGhostHunter\Laraguard\TwoFactorAuthentication;
use DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class UserTwoFactorStub extends Model implements TwoFactorAuthenticatable, AuthenticatableContract
{
    use TwoFactorAuthentication, Authenticatable;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];
}
