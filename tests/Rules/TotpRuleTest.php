<?php

namespace Tests\Rules;

use Tests\Stubs\UserStub;
use Tests\RegistersPackage;
use Tests\CreatesTwoFactorUser;
use Orchestra\Testbench\TestCase;
use Tests\RunsPublishableMigrations;
use Illuminate\Support\Facades\Date;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class TotpRuleTest extends TestCase
{
    use RegistersPackage;
    use DatabaseMigrations;
    use RunsPublishableMigrations;
    use CreatesTwoFactorUser;

    protected function setUp() : void
    {
        $this->afterApplicationCreated([$this, 'loadLaravelMigrations']);
        $this->afterApplicationCreated([$this, 'runPublishableMigration']);
        $this->afterApplicationCreated([$this, 'createTwoFactorUser']);

        $this->afterApplicationCreated(function () {
            $this->app['router']->get('intended', function () {
                return 'ok';
            })->name('intended')->middleware('web', 'auth', '2fa.confirm');
        });

        parent::setUp();
    }

    public function test_validation_fails_if_guest(): void
    {
        $fails = validator([
            'totp_code' => '123456'
        ], [
            'totp_code' => 'totp_code'
        ])->fails();

        $this->assertTrue($fails);
    }

    public function test_validation_fails_if_user_is_not_2fa(): void
    {
        $user = UserStub::create([
            'name'     => 'test',
            'email'    => 'bar@test.com',
            'password' => UserStub::PASSWORD_SECRET,
        ]);

        $this->app['auth']->guard()->setUser($user);

        $fails = validator([
            'totp_code' => '123456'
        ], [
            'totp_code' => 'totp_code'
        ])->fails();

        $this->assertTrue($fails);
    }

    public function test_validator_fails_if_user_is_2fa_but_not_enabled(): void
    {
        $this->app['auth']->guard()->setUser(tap($this->user)->disableTwoFactorAuth());

        $fails = validator([
            'totp_code' => '123456'
        ], [
            'totp_code' => 'totp_code'
        ])->fails();

        $this->assertTrue($fails);
    }

    public function test_validator_fails_if_user_is_2fa_but_code_is_invalid(): void
    {
        $this->app['auth']->guard()->setUser($this->user);

        $fails = validator([
            'totp_code' => '123456'
        ], [
            'totp_code' => 'totp_code'
        ])->fails();

        $this->assertTrue($fails);
    }

    public function test_validator_fails_if_user_is_2fa_but_code_is_expired_over_window(): void
    {
        Date::setTestNow($now = Date::create(2020, 04, 01, 16, 30));

        $this->app['auth']->guard()->setUser($this->user);

        $fails = validator([
            'totp_code' => $this->user->makeTwoFactorCode($now, -2)
        ], [
            'totp_code' => 'totp_code'
        ])->fails();

        $this->assertTrue($fails);
    }

    public function test_validator_succeeds_if_code_valid(): void
    {
        Date::setTestNow($now = Date::create(2020, 04, 01, 16, 30));

        $this->app['auth']->guard()->setUser($this->user);

        $fails = validator([
            'totp_code' => $this->user->makeTwoFactorCode($now)
        ], [
            'totp_code' => 'totp_code'
        ])->fails();

        $this->assertFalse($fails);
    }

    public function test_validator_succeeds_if_code_is_recovery(): void
    {
        $this->app['auth']->guard()->setUser($this->user);

        $fails = validator([
            'totp_code' => $this->user->generateRecoveryCodes()->first()['code']
        ], [
            'totp_code' => 'totp_code'
        ])->fails();

        $this->assertFalse($fails);
    }

    public function test_validator_rule_uses_translation(): void
    {
        $validator = validator([
            'totp_code' => 'invalid'
        ], [
            'totp_code' => 'totp_code'
        ]);

        $this->assertSame('The Code is invalid or has expired.', $validator->errors()->get('totp_code')[0]);
    }

}
