<?php

namespace Tests;

use Tests\Stubs\UserStub;
use Tests\Stubs\UserTwoFactorStub;
use DarkGhostHunter\Laraguard\Eloquent\TwoFactorAuthentication;

trait CreatesTwoFactorUser
{
    /** @var \Tests\Stubs\UserTwoFactorStub */
    protected $user;

    protected function createTwoFactorUser(): void
    {
        $this->user = UserTwoFactorStub::create([
            'name'     => 'foo',
            'email'    => 'foo@test.com',
            'password' => UserStub::PASSWORD_SECRET,
        ]);

        $this->user->twoFactorAuth()->save(
            TwoFactorAuthentication::factory()->make()
        );
    }
}
