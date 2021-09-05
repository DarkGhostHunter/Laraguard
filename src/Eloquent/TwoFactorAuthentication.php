<?php

namespace DarkGhostHunter\Laraguard\Eloquent;

use DarkGhostHunter\Laraguard\Contracts\TwoFactorTotp;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use ParagonIE\ConstantTime\Base32;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder
 *
 * @property-read int $id
 *
 * @property-read null|\DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable $authenticatable
 *
 * @property string $shared_secret
 * @property string $label
 * @property int $digits
 * @property int $seconds
 * @property int $window
 * @property string $algorithm
 * @property array $totp_config
 * @property null|\Illuminate\Support\Collection $recovery_codes
 * @property null|\Illuminate\Support\Collection $safe_devices
 * @property null|\Illuminate\Support\Carbon|\DateTime $enabled_at
 * @property null|\Illuminate\Support\Carbon|\DateTime $recovery_codes_generated_at
 *
 * @property null|\Illuminate\Support\Carbon|\DateTime $updated_at
 * @property null|\Illuminate\Support\Carbon|\DateTime $created_at
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
        'recovery_codes'              => 'encrypted:collection',
        'safe_devices'                => 'collection',
        'enabled_at'                  => 'datetime',
        'recovery_codes_generated_at' => 'datetime',
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
     *
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
        return $this->enabled_at !== null;
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
     * @return $this
     */
    public function flushAuth(): static
    {
        $this->recovery_codes_generated_at = null;
        $this->safe_devices = null;
        $this->enabled_at = null;

        $this->attributes = array_merge($this->attributes, config('laraguard.totp'));

        $this->shared_secret = static::generateRandomSecret();
        $this->recovery_codes = null;

        return $this;
    }

    /**
     * Creates a new Random Secret.
     *
     * @return string
     */
    public static function generateRandomSecret(): string
    {
        return Base32::encodeUpper(
            random_bytes(config('laraguard.secret_length'))
        );
    }
}
