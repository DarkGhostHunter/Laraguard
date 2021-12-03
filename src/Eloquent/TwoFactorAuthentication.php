<?php

namespace DarkGhostHunter\Laraguard\Eloquent;

use DarkGhostHunter\Laraguard\Contracts\TwoFactorTotp;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use JetBrains\PhpStorm\Pure;
use ParagonIE\ConstantTime\Base32;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder
 *
 * @property-read int $id
 *
 * @property-read \DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable|null $authenticatable
 *
 * @property string $shared_secret
 * @property string $label
 * @property int $digits
 * @property int $seconds
 * @property int $window
 * @property string $algorithm
 * @property array $totp_config
 * @property \Illuminate\Support\Collection<array<string,int|null>>|null $recovery_codes
 * @property \Illuminate\Support\Collection<array<string,string,int>>|null $safe_devices
 * @property \Illuminate\Support\Carbon|\DateTime|null $enabled_at
 * @property \Illuminate\Support\Carbon|\DateTime|null $recovery_codes_generated_at
 *
 * @property \Illuminate\Support\Carbon|\DateTime|null $updated_at
 * @property \Illuminate\Support\Carbon|\DateTime|null $created_at
 *
 * @method static \Database\Factories\DarkGhostHunter\Laraguard\Eloquent\TwoFactorAuthenticationFactory factory(...$parameters)
 */
class TwoFactorAuthentication extends Model implements TwoFactorTotp
{
    use HandlesCodes;
    use HandlesRecoveryCodes;
    use HandlesSafeDevices;
    use SerializesSharedSecret;
    use HasFactory;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'shared_secret'               => 'encrypted',
        'authenticatable_id'          => 'int',
        'digits'                      => 'int',
        'seconds'                     => 'int',
        'window'                      => 'int',
        'enabled_at'                  => 'datetime',
        'recovery_codes_generated_at' => 'datetime',

        'recovery_codes'              => AsEncryptedCollection::class,
        'safe_devices'                => AsCollection::class,
    ];

    /**
     * The model that uses Two-Factor Authentication.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function authenticatable(): MorphTo
    {
        return $this->morphTo('authenticatable');
    }

    /**
     * Sets the Algorithm to lowercase.
     *
     * @param $value
     * @return void
     */
    protected function setAlgorithmAttribute($value): void
    {
        $this->attributes['algorithm'] = strtolower($value);
    }

    /**
     * Returns if the Two-Factor Authentication has been enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return isset($this->attributes['enabled_at']);
    }

    /**
     * Returns if the Two-Factor Authentication is not been enabled.
     *
     * @return bool
     */
    public function isDisabled(): bool
    {
        return !$this->isEnabled();
    }

    /**
     * Flushes all authentication data and cycles the Shared Secret.
     *
     * @param  bool  $save  If at flushing should also save the model.
     * @return $this
     */
    public function flushAuth(bool $save = true): static
    {
        $this->attributes['recovery_codes_generated_at'] = null;
        $this->safe_devices = null;
        $this->attributes['enabled_at'] = null;
        $this->shared_secret = static::generateRandomSecret();
        $this->recovery_codes = null;

        $this->attributes = array_merge($this->attributes, config('laraguard.totp'));

        if ($save) {
            $this->save();
        }

        return $this;
    }

    /**
     * Creates a new Random Secret.
     *
     * @return string
     */
    public static function generateRandomSecret(): string
    {
        return Base32::encodeUpper(random_bytes(config('laraguard.secret_length')));
    }
}
