<?php

namespace Tests;

trait RunsPublishableMigrations
{
    protected function runPublishableMigration(): void
    {
        $this->loadMigrationsFrom([
                '--realpath' => true,
                '--path' => [
                    realpath(__DIR__ . '/../database/migrations/2020_04_02_000000_create_two_factor_authentications_table.php')
                ]
        ]);
    }
}
