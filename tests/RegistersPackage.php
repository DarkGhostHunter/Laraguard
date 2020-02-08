<?php

namespace Tests;

use DarkGhostHunter\Laraguard\LaraguardServiceProvider;

trait RegistersPackage
{
    protected function getPackageProviders($app)
    {
        return [LaraguardServiceProvider::class];
    }
}
