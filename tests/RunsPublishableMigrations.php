<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;

trait RunsPublishableMigrations
{
    use DatabaseMigrations;

    protected function runPublishableMigration()
    {
        $this->loadMigrationsFrom([
                '--realpath' => true,
                '--path' => [
                    realpath(__DIR__ . '/../database/migrations')
                ]
        ]);
    }
}
