<?php

namespace Tests\Eloquent;

use DarkGhostHunter\Laraguard\Eloquent\TwoFactorAuthentication;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Orchestra\Testbench\TestCase;
use Tests\RegistersPackage;

class UpgradeTest extends TestCase
{
    use RegistersPackage;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists('UpgradeTwoFactorAuthenticationsTable')) {
            require_once __DIR__ . '/../../database/migrations/2020_04_02_000000_upgrade_two_factor_authentications_table.php';
        }
    }

    public function test_migrates_old_table_and_records(): void
    {
        Schema::create('two_factor_authentications', function (Blueprint $table) {
            $table->id();
            $table->string('shared_secret');
            $table->string('recovery_codes')->nullable();
            $table->timestampsTz();
        });

        DB::table('two_factor_authentications')->insert([
            'shared_secret' => $secret = Str::random(300),
            'recovery_codes' => $codes = Collection::make(['foo' => 'bar']),
        ]);

        DB::table('two_factor_authentications')->insert([
            'shared_secret' => $secret,
            'recovery_codes' => null,
        ]);

        (new \UpgradeTwoFactorAuthenticationsTable)->up();

        static::assertSame($secret, TwoFactorAuthentication::find(1)->shared_secret);
        static::assertSame($codes->all(), TwoFactorAuthentication::find(1)->recovery_codes->all());
        static::assertNull(TwoFactorAuthentication::find(2)->recovery_codes);

        static::assertSame(
            'text',
            DB::connection()->getDoctrineColumn('two_factor_authentications', 'shared_secret')->getType()->getName()
        );

        static::assertSame(
            'text',
            DB::connection()->getDoctrineColumn('two_factor_authentications', 'recovery_codes')->getType()->getName()
        );
    }

    public function test_rollbacks_migration(): void
    {
        Schema::create('two_factor_authentications', function (Blueprint $table) {
            $table->id();
            $table->text('shared_secret');
            $table->text('recovery_codes')->nullable();
            $table->timestampsTz();
        });

        DB::table('two_factor_authentications')->insert([
            'shared_secret' => Crypt::encryptString($secret = Str::random(300)),
            'recovery_codes' => Crypt::encryptString($codes = Collection::make(['foo' => 'bar'])),
        ]);

        DB::table('two_factor_authentications')->insert([
            'shared_secret' => Crypt::encryptString($secret),
            'recovery_codes' => null,
        ]);

        (new \UpgradeTwoFactorAuthenticationsTable)->down();

        static::assertSame($secret, DB::table('two_factor_authentications')->where('id', 1)->first()->shared_secret);
        static::assertSame($codes->toJson(), DB::table('two_factor_authentications')->where('id', 1)->first()->recovery_codes);
        static::assertNull(DB::table('two_factor_authentications')->where('id', 2)->first()->recovery_codes);

        static::assertSame(
            'string',
            DB::connection()->getDoctrineColumn('two_factor_authentications', 'shared_secret')->getType()->getName()
        );

        static::assertSame(
            'json',
            DB::connection()->getDoctrineColumn('two_factor_authentications', 'recovery_codes')->getType()->getName()
        );
    }
}
