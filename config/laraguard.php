<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Listener hook
    |--------------------------------------------------------------------------
    |
    | If the Listener is enabled, Laraguard will automatically hook into the
    | "Attempting" event and magically ask for Two Factor Authentication if
    | is necessary. Disable this to use your own 2FA authentication logic.
    |
    */

    'listener' => true,

    /*
    |--------------------------------------------------------------------------
    | Input name
    |--------------------------------------------------------------------------
    |
    | When using the Listener, it will automatically check the Request for the
    | input name containing the Two Factor Code. A safe default is set here,
    | but you can override the value if it collides with other form input.
    |
    */

    'input' => '2fa_code',

    /*
    |--------------------------------------------------------------------------
    | Recovery Codes
    |--------------------------------------------------------------------------
    |
    | This option controls the recovery codes generation. By default is enabled
    | so users have a way to authenticate without a code generator. The length
    | of the codes, as their quantity, can be configured to tighten security.
    |
    */

    'recovery' => [
        'enabled' => true,
        'codes' => 10,
        'length' => 8,
    ],

    /*
    |--------------------------------------------------------------------------
    | Safe Devices
    |--------------------------------------------------------------------------
    |
    | Authenticating with Two Factor Codes can become very obnoxious if you do
    | it every time, so for this reasons the Safe Devices can be enabled. It
    | remembers the device with an long-lived cookie to bypass Two Factor.
    |
    */

    'safe_devices' => [
        'enabled' => false,
        'max_devices' => 3,
        'expiration_days' => 14,
    ],

    /*
    |--------------------------------------------------------------------------
    | Secret Length
    |--------------------------------------------------------------------------
    |
    | Using a shared secret with a length of 160-bit (as recommended per RFC
    | 4226) is recommended, but you may want to tighten or loose the secret
    | length. The RFC 4226 standard allows down to 128-bit shared secrets.
    |
    */

    'secret_length' => 20,

    /*
    |--------------------------------------------------------------------------
    | TOTP config
    |--------------------------------------------------------------------------
    |
    | While this package uses recommended RFC 4226 and RDC 6238 settings, you
    | can further configure how TOTP should work. These settings are saved
    | for each 2FA authentication, so it will only affect new accounts.
    |
    */

    'totp' => [
        'digits' => 6,
        'seconds' => 30,
        'window' => 1,
        'algorithm' => 'sha1',
    ],
];
