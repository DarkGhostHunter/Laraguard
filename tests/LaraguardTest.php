<?php

namespace Tests;

use DarkGhostHunter\Laraguard\Laraguard;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;
use Mockery;
use Orchestra\Testbench\TestCase;
use Tests\Stubs\UserStub;
use Tests\Stubs\UserTwoFactorStub;

class LaraguardTest extends TestCase
{
    use DatabaseMigrations;
    use RunsPublishableMigrations;
    use RegistersPackage;
    use CreatesTwoFactorUser;
    use RegistersLoginRoute;
    use WithFaker;

    protected function setUp() : void
    {
        $this->afterApplicationCreated([$this, 'loadLaravelMigrations']);
        $this->afterApplicationCreated([$this, 'runPublishableMigration']);
        $this->afterApplicationCreated([$this, 'registerLoginRoute']);
        $this->afterApplicationCreated([$this, 'createTwoFactorUser']);
        $this->afterApplicationCreated(function (): void {
            app('config')->set('auth.providers.users.model', UserTwoFactorStub::class);
            $this->travelTo(today());
        });
        parent::setUp();
    }

    public function test_authenticates_with_when(): void
    {
        $credentials = [
            'email' => $this->user->email,
            'password' => 'secret'
        ];

        $this->instance('request', Request::create('test', 'POST', [
            '2fa_code' => $this->user->makeTwoFactorCode()
        ]));

        static::assertTrue(Auth::attemptWhen($credentials, Laraguard::hasCode()));
    }

    public function test_authenticates_with_when_with_no_exceptions(): void
    {
        $credentials = [
            'email' => $this->user->email,
            'password' => 'secret'
        ];

        $this->instance('request', Request::create('test', 'POST', [
            '2fa_code' => $this->user->makeTwoFactorCode()
        ]));

        static::assertTrue(Auth::attemptWhen($credentials, Laraguard::hasCodeOrFails()));
    }

    public function test_authenticates_with_different_input_name(): void
    {
        $credentials = [
            'email' => $this->user->email,
            'password' => 'secret'
        ];

        $this->instance('request', Request::create('test', 'POST', [
            'foo_bar' => $this->user->makeTwoFactorCode()
        ]));

        static::assertTrue(Auth::attemptWhen($credentials, Laraguard::hasCode('foo_bar')));
    }

    public function test_doesnt_authenticates_with_invalid_code(): void
    {
        $credentials = [
            'email' => $this->user->email,
            'password' => 'secret'
        ];

        $this->instance('request', Request::create('test', 'POST', [
            '2fa_code' => 'invalid'
        ]));

        static::assertFalse(Auth::attemptWhen($credentials, Laraguard::hasCode()));
    }

    public function test_non_two_factor_user_doesnt_authenticate(): void
    {
        $user = UserStub::create([
            'name'     => 'bar',
            'email'    => 'bar@test.com',
            'password' => UserStub::PASSWORD_SECRET,
        ]);

        $credentials = [
            'email' => $user->email,
            'password' => 'secret'
        ];

        $this->instance('request', Request::create('test', 'POST', [
            '2fa_code' => $this->user->makeTwoFactorCode()
        ]));

        static::assertFalse(Auth::attemptWhen($credentials, Laraguard::hasCode()));
    }

    public function test_validation_exception_when_code_invalid(): void
    {
        $this->expectException(ValidationException::class);

        $credentials = [
            'email' => $this->user->email,
            'password' => 'secret'
        ];

        $this->instance('request', Request::create('test', 'POST', [
            '2fa_code' => 'invalid'
        ]));

        try {
            Auth::attemptWhen($credentials, Laraguard::hasCodeOrFails());
        } catch (ValidationException $exception) {
            static::assertSame(['2fa_code' => ['The Code is invalid or has expired.']], $exception->errors());
            throw $exception;
        }
    }

    public function test_validation_exception_with_message_when_code_invalid(): void
    {
        $this->expectException(ValidationException::class);

        $credentials = [
            'email' => $this->user->email,
            'password' => 'secret'
        ];

        $this->instance('request', Request::create('test', 'POST', [
            '2fa_code' => 'invalid'
        ]));

        try {
            Auth::attemptWhen($credentials, Laraguard::hasCodeOrFails(message: 'foo'));
        } catch (ValidationException $exception) {
            static::assertSame(['2fa_code' => ['foo']], $exception->errors());
            throw $exception;
        }
    }

    public function test_saves_safe_device(): void
    {
        config()->set('laraguard.safe_devices.enabled', true);

        Cookie::partialMock()->shouldReceive('queue')
            ->with('2fa_remember', Mockery::type('string'), 14 * 1440)
            ->once();

        $credentials = [
            'email' => $this->user->email,
            'password' => 'secret'
        ];

        $this->instance('request', Request::create('test', 'POST', [
            '2fa_code' => $this->user->makeTwoFactorCode(),
            'safe_device' => 'on',
        ]));

        static::assertTrue(Auth::attemptWhen($credentials, Laraguard::hasCode()));
        static::assertCount(1, $this->user->fresh()->safeDevices());
    }

    public function test_doesnt_adds_safe_device_when_input_not_filled(): void
    {
        config()->set('laraguard.safe_devices.enabled', true);

        Cookie::partialMock()->shouldNotReceive('queue');

        $credentials = [
            'email' => $this->user->email,
            'password' => 'secret'
        ];

        $this->instance('request', Request::create('test', 'POST', [
            '2fa_code' => $this->user->makeTwoFactorCode(),
        ]));

        static::assertTrue(Auth::attemptWhen($credentials, Laraguard::hasCode()));

        static::assertEmpty($this->user->fresh()->safeDevices());
    }

    public function test_doesnt_bypasses_totp_if_safe_devices(): void
    {
        config()->set('laraguard.safe_devices.enabled', true);

        $credentials = [
            'email' => $this->user->email,
            'password' => 'secret'
        ];

        $this->instance('request', $request = Request::create('test', 'POST'));

        $token = $this->user->addSafeDevice($request);

        $request->cookies->set('2fa_remember', $token);

        static::assertTrue(Auth::attemptWhen($credentials, Laraguard::hasCode()));
    }
}
