<?php

use Composer\InstalledVersions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpgradeTwoFactorAuthenticationsTable extends Migration
{
    /**
     * Creates a new Migration instance.
     *
     * @return void
     */
    public function __construct()
    {
        if (! InstalledVersions::isInstalled('doctrine/dbal')) {
            throw new OutOfBoundsException("Install the doctrine/dbal package to upgrade or downgrade.");
        }
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('two_factor_authentications', static function (Blueprint $table): void {
            $table->text('shared_secret')->change();
            $table->text('recovery_codes')->nullable()->change();
        });

        // We need to encrypt all shared secrets so these can be used with Laraguard v4.0.
        $this->chunkRows(true);
    }

    /**
     * Returns a chunk of authentications to encrypt/decrypt them.
     *
     * @param  bool  $encrypt
     *
     * @return void
     */
    protected function chunkRows(bool $encrypt): void
    {
        $call = $encrypt ? 'encryptString' : 'decryptString';
        $encrypter = Crypt::getFacadeRoot();
        $query = DB::table('two_factor_authentications');

        $query->clone()->select('id', 'shared_secret', 'recovery_codes')
            ->chunkById(
                1000,
                static function (Collection $chunk) use ($encrypter, $query, $call): void {
                    DB::beginTransaction();
                    foreach ($chunk as $item) {
                        $query->clone()->where('id', $item->id)->update([
                            'shared_secret'  => $encrypter->$call($item->shared_secret),
                            'recovery_codes' => $item->recovery_codes ? $encrypter->$call($item->recovery_codes) : null,
                        ]);
                    }
                    DB::commit();
                }
            );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        // Before changing the shared secret column, we will need to decrypt the shared secret.
        $this->chunkRows(false);

        Schema::table('two_factor_authentications', static function (Blueprint $table): void {
            $table->string('shared_secret')->change();
            $table->json('recovery_codes')->nullable()->change();
        });
    }
}
