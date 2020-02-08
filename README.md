![Zachary Lisko - Unsplash (UL) #JEBeXUHm1c4](https://images.unsplash.com/flagged/photo-1570343271132-8949dd284a04?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=crop&w=1280&h=400&q=80)

# Laraguard

[![Latest Version on Packagist](https://img.shields.io/packagist/v/darkghosthunter/laraguard.svg?style=flat-square)](https://packagist.org/packages/darkghosthunter/laraguard)
[![Build Status](https://img.shields.io/travis/darkghosthunter/laraguard/master.svg?style=flat-square)](https://travis-ci.org/darkghosthunter/laraguard)
[![Quality Score](https://img.shields.io/scrutinizer/g/darkghosthunter/laraguard.svg?style=flat-square)](https://scrutinizer-ci.com/g/darkghosthunter/laraguard)
[![Total Downloads](https://img.shields.io/packagist/dt/darkghosthunter/laraguard.svg?style=flat-square)](https://packagist.org/packages/darkghosthunter/laraguard)

Two Factor Authentication via TOTP for all your Users out-of-the-box.

This package _silently_ enables authentication using 6 digits codes, without Internet or external providers.

## Table of Contents

* [Installation](#installation)
    + [How this works](#how-this-works)
* [Usage](#usage)
    + [Enabling Two Factor Authentication](#enabling-two-factor-authentication)
    + [Recovery Codes](#recovery-codes)
    + [Logging in](#logging-in)
    + [Deactivation](#deactivation)
* [Events](#events)
* [Middleware](#middleware)
* [Protecting the Login](#protecting-the-login)
* [Configuration](#configuration)
    + [Listener](#listener)
    + [Recovery](#recovery)
    + [Safe devices](#safe-devices)
    + [Secret length](#secret-bytes)
    + [TOTP configuration](#totp-configuration)
    + [Custom view](#custom-view)
    + [Input name](#input-name)
* [Security](#security)
* [License](#license)

## Installation

Fire up Composer and require this package in your project.

    composer require darkghosthunter/laraguard

That's it.

### How this works

This packages adds a **Contract** to detect if the model should use Two Factor Authentication, in a **per-user basis**. It also uses a custom **view** and **listener** to handle the Two Factor authentication itself during login attempts.

It _tries_ to be the less invasive possible, but you can go full manual if you want.

## Usage

First, run the migrations. This will create a table to handle Two Factor Authentication information for each model you set.

    php artisan migrate:run

Add the `TwoFactorAuthenticatable` _contract_ and the `TwoFactorAuthentication` trait to the User model, or any other model you want to use Two Factor Authentication. 

```php
<?php

namespace App;

use Illuminate\Foundation\Auth\User as Authenticatable;
use DarkGhostHunter\Laraguard\TwoFactorAuthentication;
use DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable;

class User extends Authenticatable implements TwoFactorAuthenticatable
{
    use TwoFactorAuthentication;
    
    // ...
}
```

The contract is used to identify the model using Two Factor Authentication, while the trait conveniently implements the methods required to handle it.

### Enabling Two Factor Authentication

To enable Two Factor Authentication successfully, the User must sync the Shared Secret between its Authenticator app and the application. 

> Some free Authenticator Apps are [FreeOTP](https://freeotp.github.io/), [Authy](https://authy.com/), [andOTP](https://github.com/andOTP/andOTP), [Google Authenticator](https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2&hl=en), and [Microsoft Authenticator](https://www.microsoft.com/en-us/account/authenticator), to name a few.

To generate a shared secret, use the `createSharedSecret()` method. Once you do, you can show it to the user as a string or QR Code (encoded as SVG) in your view.

```php
public function prepareTwoFactor(Request $request)
{
    $secret = $request->user()->createSharedSecret();
    
    return view('user.2fa', [
        'as_qr_code' => $secret->toQr(),     // As QR Code
        'as_uri'     => $secret->toUri(),    // As "otpauth://" URI.
        'as_string'  => $secret->toString(), // As a string
    ]);
}
```

Finally, the user must confirm the Shared Secret with a Code. This package will automatically enable Two Factor Authentication for the user if the code is confirmed using `confirmSharedSecret()`, not before.

```php
public function confirmTwoFactor(Request $request)
{
    return $request->user()->confirmSharedSecret(
        $request->input('2fa_code')
    );
}
```

If the user doesn't issue the correct Code, the method will return `false`. You can tell him to double-check its device's timezone, or create another Shared Secret with `createSharedSecret()`.

> Every time you use `createSharedSecret()` the previous Two Factor Authentication becomes permanently invalid. The user should never have two shared secrets enabled at any given time.

### Recovery Codes

Recovery Codes are automatically generated each time the Two Factor Authentication is enabled. By default, a Collection of ten one-use 8-characters codes are created.

You can show them using `getRecoveryCodes()`.

```php
public function confirmTwoFactor(Request $request)
{
    if ($request->user()->confirmSharedSecret($code)) {
        return $request->user()->getRecoveryCodes();
    } else {
        return 'Try again!';
    }
}
```

You're free on how to show these codes to the user, but **ensure** you show them one time after a successfully enabling Two Factor Authentication, and ask him to print them somewhere.

> These Recovery Codes are handled automatically when the user issues a Code. If it's a recovery code, the package will use it and invalidate it.

The user can generate a fresh new batch of codes using `generateRecoveryCodes()`, invalidating the previous codes.

```php
public function generateRecoveryCodes(Request $request)
{
    return $request->user()->generateRecoveryCodes();
}
```

> If the user depletes his recovery codes without disabling Two Factor Authentication, or Recovery Codes are deactivated, **he may be locked out forever without his Authenticator app**. Ensure you have countermeasures in these cases.

### Logging in

This package hooks into the `Validated` event (or `Attempting` if it doesn't exists) to check the user's Two Factor Authentication configuration preemptively.

1. If the User has set up Two Factor Authentication, it will be prompted for a 2FA Code, otherwise authentication will proceed as normal.
2. If the Login attempt contains a `2fa_code` with the 2FA Code inside the Request, it will be used to check if its valid.

This is done transparently without intervening your application with guards, routes, controllers actions or middleware.

Additionally, [protect your login route](#protecting-the-login).

> If you're using a custom Authentication Guard that doesn't fire events, this package won't work, like the `TokenGuard` and the `RequestGuard`.

### Deactivation

You can deactivate Two Factor Authentication for a given user using the `disableTwoFactorAuth()` method. This will automatically invalidate the Shared Secret and Recovery Codes, allowing the user to log in with just his credentials.

```php
public function disableTwoFactor(Request $request)
{
    $request->user()->disableTwoFactorAuth();
}
```

## Events

The following events are fired once a user is Logged in, in addition to the default Authentication events.

* `TwoFactorEnabled`: An user has enabled Two Factor Authentication.
* `TwoFactorRecoveryCodesDepleted`: An user has used his last Recovery Code.
* `TwoFactorRecoveryCodesGenerated`: An user has generated a new set of Recovery Codes.
* `TwoFactorDisabled`: An user has disabled Two Factor Authentication.

> You can use `TwoFactorRecoveryCodesDepleted` to notify the user to create more Recovery Codes, or even disable Two Factor Auth automatically to prevent the user being locked out.

## Middleware

If you need to ensure the User has Two Factor Authentication enabled before entering a given route, you can use the `2fa` middleware.

```php
Route::get('system/settings')
    ->uses('SystemSettingsController@show')
    ->middleware('2fa');
```

This middleware works much like the `verified` middleware. If the user has not enabled Two Factor Authentication, it will be redirected to a route name containing the warning, which is `2fa.notice` by default.

## Protecting the Login

Two Factor Authentication can be victim of brute-force attacks. The attacker will need at best 33.333 requests each second to get the correct codes.

Since the listener throws a response before the Login throttler increments its tries, its recommended to use a try-catch in the `attemptLogin()` method.

```php
/**
 * Attempt to log the user into the application.
 *
 * @param  \Illuminate\Http\Request  $request
 * @return bool
 */
protected function attemptLogin(Request $request)
{
    try {
        return $this->guard()->attempt(
            $this->credentials($request), $request->filled('remember')
        );
    } catch (HttpResponseException $exception) {
        $this->incrementLoginAttempts($request);
        throw $exception;
    }
}
```  

To show the form, the Listener uses `HttpResponseException` to forcefully exit the authentication and show the form. This allows to throw the response after the login attempts are incremented.

## Configuration

To further configure the package, publish the configuration files and assets:

    php artisan vendor:publish --provider=DarkGhostHunter\Laraguard\LaraguardServiceProvider

You will receive the authentication view in `resources/views/vendor/laraguard/auth.blade.php`, and the `config/laraguard.php` config file with the following contents:

```php
return [
    'listener' => true,
	'recovery' => [
	    'enabled' => true,
		'codes' => 10,
		'length' => 8,
	],
	'safe_devices' => [
	    'enabled' => false,
		'max_devices' => 3,
		'expiration_days' => 14,
	],    
	'secret_length' => 20,
    'totp' => [
        'digits' => 6,
        'seconds' => 30,
        'window' => 1,
        'algorithm' => 'sha1',
    ],
];
```

### Listener

```php
return [
    'listener' => true,
];
```

This package works by hooking up the `ForcesTwoFactorAuth` listener to the `Attempting` event.

This may work wonders out-of-the-box, but if you want tidier control on how and when prompt for Two Factor Authentication, you can disable it. For example, to create your own 2FA Guard or modify the Login Controller.

### Recovery

```php
return [
	'recovery' => [
	    'enabled' => true,
		'codes' => 10,
		'length' => 8,
	],
];
```

You can disable the generation and checking of Recovery Codes. If you do, ensure users can authenticate by other means, like sending an email with a link to a signed URL that logs him in and disables Two Factor Authentication.

The number and length of codes generated is configurable. 10 Codes of 8 random characters are enough for most authentication scenarios.

### Safe devices

```php
return [
	'safe_devices' => [
	    'enabled' => false,
		'max_devices' => 3,
		'expiration_days' => 14,
	],
];
```

Enabling this option will allow the application to "remember" a device using a cookie, bypassing Two Factor Authentication once a code is verified in that device. 

There is a limit of devices that can be saved. New devices will displace the oldest devices registered. Devices are considered no longer "safe" until a set amount of days.

You can change the maximum number of devices saved and the amount of days of validity once they're registered. More devices and more expiration days will make the Two Factor Authentication less secure.

> When re-enabling Two Factor Authentication, the list of devices is automatically invalidated.

### Secret length

```php
return [
	'secret_length' => 20,
];
```

This controls how the length (in bytes) used to create the Shared Secret. While 160-bit shared secret are enough for most authentication scenarios, you can tighten or loosen the secret length.

It's recommended the default because some Authenticator apps may have some problems with larger or shorter lengths.

### TOTP Configuration 

```php
return [
    'totp' => [
        'digits' => 6,
        'seconds' => 30,
        'window' => 1,
        'algorithm' => 'sha1',
    ],
];
```

This controls TOTP code generation and verification mechanisms:

* Digits: The amount of digits to ask for TOTP code. 
* Seconds: The number of seconds a code is considered valid.
* Window: Additional steps of seconds to keep a code as valid.
* Algorithm: The system-supported algorithm to handle code generation.

This configuration values are always passed down to the authentication app as URI parameters:

    otpauth://totp/Laravel:taylor@laravel.com?secret=THISISMYSECRETPLEASEDONOTSHAREIT&issuer=Laravel&algorithm=sha1&digits=6&period=30

These values are printed to each 2FA data inside the application. Changes will only take effect for new activations.

> It's not recommended to edit these parameters if you plan to use publicly available Authenticator apps, since some of them **may not support non-standard configuration**, like more digits, different seconds counter or other algorithms.

### Custom view

You can override the `resources/views/vendor/laraguard/auth.blade.php` view, which handles the Two Factor Code verification for the user. It receives this data:

* `$action`: The full URL where the form should send the login credentials. 
* `$credentials`: An array containing the user credentials used for login.
* `$user`: The User trying to authenticate.
* `$remember`: Optional. If present, the "remember" checkbox value (`on`).
* `$error`: If the Two Factor Code is invalid. 

The way it works is very simple: it will hold the user credentials in a hidden input while it asks for the Two Factor Code. The user will send the everything again, the application will ensure the Code is correct, and complete the log in.

This view and its controller is bypassed if the user has not enabled Two Factor Authentication, making the log in transparent and non-invasive. 

### Input name

By default, the input name that must contain the Two Factor Authentication Code is called `2fa_code`, which is a good default value to avoid collisions with other inputs names. This allows to seamlessly intercept the log in attempt and proceed with Two Factor Authentication or bypass it.

If you wish to change the input name, instruct the Service Container to use the input name you want when resolving the `ForcesTwoFactorAuth` listener inside your application Service Provider.

```php
public function boot()
{
    $this->app->when('DarkGhostHunter\Laraguard\Listeners\ForcesTwoFactorAuth')
        ->needs('$input')
        ->give('2fa_code_input');
    
    // ...
}
```

## Security

If you discover any security related issues, please email darkghosthunter@gmail.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
