<?php

namespace Database\Factories\DarkGhostHunter\Laraguard\Eloquent;

use Faker\Generator as Faker;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;
use DarkGhostHunter\Laraguard\Eloquent\TwoFactorAuthentication;

class TwoFactorAuthenticationFactory extends Factory {
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TwoFactorAuthentication::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $config = config('laraguard');

        $array = array_merge([
            'shared_secret' => TwoFactorAuthentication::generateRandomSecret(),
            'enabled_at'    => $this->faker->dateTimeBetween('-1 year'),
            'label'         => $this->faker->freeEmail,
        ], $config['totp']);

        [$enabled, $amount, $length] = array_values($config['recovery']);

        if ($enabled) {
            $array['recovery_codes'] = TwoFactorAuthentication::generateRecoveryCodes($amount, $length);
            $array['recovery_codes_generated_at'] = $this->faker->dateTimeBetween('-1 years');
        }

        return $array;
    }

    /**
     * Returns a model with unused recovery codes.
     *
     * @return TwoFactorAuthenticationFactory
     */
    public function withRecovery()
    {
        return $this->state(function(array $attributes) {
            [$enabled, $amount, $length] = array_values(config('laraguard.recovery'));

            return [
                'recovery_codes'              => TwoFactorAuthentication::generateRecoveryCodes($amount, $length),
                'recovery_codes_generated_at' => $this->faker->dateTimeBetween('-1 years'),
            ];
        });
    }

    /**
     * Returns an authentication with a list of safe devices.
     *
     * @return TwoFactorAuthenticationFactory
     */
    public function withSafeDevices()
    {
        return $this->state(function (array $attributes) {
            $max = config('laraguard.safe_devices.max_devices');

            return [
                'safe_devices' => Collection::times($max, function ($step) use ($max) {

                    $expiration_days = config('laraguard.safe_devices.expiration_days');

                    $added_at = $max !== $step
                        ? now()
                        : $this->faker->dateTimeBetween(now()->subDays($expiration_days * 2), now()->subDays($expiration_days));

                    return [
                        '2fa_remember' => TwoFactorAuthentication::generateDefaultTwoFactorRemember(),
                        'ip'           => $this->faker->ipv4,
                        'added_at'     => $added_at,
                    ];
                }),
            ];
        });
    }

    /**
     * Returns an enabled authentication.
     *
     * @return TwoFactorAuthenticationFactory
     */
    public function enabled()
    {
        return $this->state(function (array $attributes) {
            return [
                'enabled_at' => null
            ];
        });
    }
}
