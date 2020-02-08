<?php

namespace Tests;

use Tests\Stubs\UserTwoFactorStub;
use DarkGhostHunter\Laraguard\Eloquent\TwoFactorAuthentication;

trait CreatesTwoFactorUser
{
    /** @var \Tests\Stubs\UserTwoFactorStub */
    protected $user;

    protected function createTwoFactorUser()
    {
        $this->user = UserTwoFactorStub::create([
            'name'     => 'foo',
            'email'    => 'foo@test.com',
            'password' => '$2y$10$EicEv29xyMt/AbuWc0AIkeWb8Ip0fdhAYqgiXUaoG8Klu43521jQW',
        ]);

        $this->user->twoFactorAuth()->save(
            factory(TwoFactorAuthentication::class)->make()
        );
    }
}
