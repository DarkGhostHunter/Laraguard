<?php

namespace Tests\Listeners;

use OTPHP\TOTP;
use Tests\Stubs\UserStub;
use Tests\RegistersPackage;
use Illuminate\Support\Str;
use Tests\RegistersLoginRoute;
use Illuminate\Support\Carbon;
use Tests\CreatesTwoFactorUser;
use Orchestra\Testbench\TestCase;
use Tests\Stubs\UserTwoFactorStub;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class ForcesTwoFactorAuthTest extends TestCase
{
    use RegistersPackage;
    use DatabaseMigrations;
    use CreatesTwoFactorUser;
    use RegistersLoginRoute;
    use WithFaker;

    protected function setUp() : void
    {
        $this->afterApplicationCreated([$this, 'loadLaravelMigrations']);
        $this->afterApplicationCreated([$this, 'createTwoFactorUser']);
        $this->afterApplicationCreated([$this, 'registerLoginRoute']);
        parent::setUp();
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', UserTwoFactorStub::class);
    }

    public function test_form_contains_action_credentials_remember_and_user()
    {
        $view = view('laraguard::auth')->with([
            'action'      => 'qux',
            'user'        => $this->user,
            'credentials' => ['foo' => 'bar'],
            'remember'    => 'on',
            'error'       => true,
        ])->render();

        $this->assertStringContainsString('action="qux"', $view);
        $this->assertStringContainsString('<input type="hidden" name="foo" value="bar">', $view);
        $this->assertStringContainsString('<input type="hidden" name="remember" value="on">', $view);
        $this->assertStringContainsString(__('The Code is invalid or has expired.'), $view);
    }

    public function test_login_with_no_2fa_no_code_succeeds()
    {
        $user = UserStub::create([
            'name'     => 'test',
            'email'    => 'bar@test.com',
            'password' => '$2y$10$EicEv29xyMt/AbuWc0AIkeWb8Ip0fdhAYqgiXUaoG8Klu43521jQW',
        ]);

        $this->post('login', [
            'email'    => $user->email,
            'password' => '12345678',
            'remember' => 'on',
        ])->assertSee('authenticated');
    }

    public function test_login_with_no_2fa_with_code_succeeds()
    {
        $user = UserStub::create([
            'name'     => 'test',
            'email'    => 'bar@test.com',
            'password' => '$2y$10$EicEv29xyMt/AbuWc0AIkeWb8Ip0fdhAYqgiXUaoG8Klu43521jQW',
        ]);

        $this->post('login', [
            'email'    => $user->email,
            'password' => '12345678',
            'remember' => 'on',
            'code'     => '123456',
        ])->assertSee('authenticated');
    }

    public function test_login_request_with_2fa_disabled_no_code_succeeds()
    {
        $this->user->disableTwoFactorAuth();

        $this->post('login', [
            'email'    => 'foo@test.com',
            'password' => '12345678',
            'remember' => 'on',
        ])->assertOk()->assertSee('authenticated');
    }

    public function test_login_request_with_2fa_no_code_shows_form()
    {
        $this->post('login', [
            'email'    => 'foo@test.com',
            'password' => '12345678',
            'remember' => 'on',
        ])
            ->assertViewIs('laraguard::auth')
            ->assertViewHasAll([
                'action'      => url('login'),
                'credentials' => [
                    'email'    => 'foo@test.com',
                    'password' => '12345678',
                ],
                'user'        => $this->user,
                'remember'    => true,
            ])
            ->assertDontSee('expired');

        $this->post('login', [
            'email'    => 'foo@test.com',
            'password' => '12345678',
        ])
            ->assertViewIs('laraguard::auth')
            ->assertViewHasAll([
                'action'      => url('login'),
                'credentials' => [
                    'email'    => 'foo@test.com',
                    'password' => '12345678',
                ],
                'user'        => $this->user,
                'remember'     => false,
            ])
            ->assertDontSee('expired');
    }

    public function test_login_request_with_2fa_with_code_succeeds()
    {
        $this->post('login', [
            'email'    => 'foo@test.com',
            'password' => '12345678',
            'remember' => 'on',
            '2fa_code' => $this->user->twoFactorAuth->makeCode(),
        ])->assertSeeText('authenticated');
    }

    public function test_login_request_from_safe_device_with_safe_device_disabled_shows_form()
    {
        $this->user->twoFactorAuth->safe_devices = collect([
            [
                '2fa_remember' => $token = Str::random(100),
                'ip'           => $this->faker->ipv4,
                'added_at'     => $this->faker->dateTimeBetween('-1 month'),
            ],
        ]);

        $this->user->twoFactorAuth->save();

        config(['laraguard.safe_devices.enabled' => false]);

        $this->withCookie('2fa_remember', $token)->post('login', [
            'email'    => 'foo@test.com',
            'password' => '12345678',
            'remember' => 'on',
        ])->assertViewIs('laraguard::auth');
    }

    public function test_login_request_from_safe_devices_with_safe_devices_enabled_saves_device()
    {
        config(['laraguard.safe_devices.enabled' => true]);

        $this->assertNull($this->user->twoFactorAuth->safe_devices);

        $this->post('login', [
            'email'    => 'foo@test.com',
            'password' => '12345678',
            'remember' => 'on',
            '2fa_code' => $this->user->twoFactorAuth->makeCode(),
        ], [
            'REMOTE_ADDR' => $ip = $this->faker->ipv4,
        ])->assertSeeText('authenticated');

        $this->user->refresh();

        $this->assertCount(1, $this->user->twoFactorAuth->safe_devices);
    }

    public function test_login_request_from_save_device_with_save_devices_doesnt_save_and_shows_form()
    {
        config(['laraguard.safe_devices.enabled' => true]);

        $this->assertNull($this->user->twoFactorAuth->safe_devices);

        $this->post('login', [
            'email'    => 'foo@test.com',
            'password' => '12345678',
            'remember' => 'on',
            '2fa_code' => 'invalid',
        ], [
            'REMOTE_ADDR' => $ip = $this->faker->ipv4,
        ])->assertViewIs('laraguard::auth');

        $this->user->refresh();

        $this->assertNull($this->user->twoFactorAuth->safe_devices);
    }

    public function test_login_request_from_safe_device_without_matching_device_shows_form()
    {
        $this->user->twoFactorAuth->safe_devices = collect([
            [
                '2fa_remember' => Str::random(100),
                'ip'           => $this->faker->ipv4,
                'added_at'     => $this->faker->dateTimeBetween('-1 month')->getTimestamp(),
            ],
        ]);

        $this->user->twoFactorAuth->save();

        config(['laraguard.safe_devices.enabled' => true]);

        $this->withCookie('2fa_remember', Str::random(100))->post('login', [
            'email'    => 'foo@test.com',
            'password' => '12345678',
            'remember' => 'on',
        ])->assertViewIs('laraguard::auth');
    }

    public function test_login_request_from_safe_device_with_matching_safe_device_succeeds()
    {
        $this->user->twoFactorAuth->safe_devices = collect([
            [
                '2fa_remember' => $token = Str::random(100),
                'ip'           => $this->faker->ipv4,
                'added_at'     => $this->faker->dateTimeBetween('-14 days')->getTimestamp(),
            ],
        ]);

        $this->user->twoFactorAuth->save();

        config(['laraguard.safe_devices.enabled' => true]);

        $this->withCookie('2fa_remember', $token)->post('login', [
            'email'    => 'foo@test.com',
            'password' => '12345678',
            'remember' => 'on',
        ])->assertOk();
    }

    public function test_auth_request_receives_no_code_shows_form()
    {
        $this->post('login', [
            'email'    => 'foo@test.com',
            'password' => '12345678',
            'remember' => 'on',
            '2fa_code' => '',
        ])->assertViewIs('laraguard::auth')->assertStatus(422);
    }

    public function test_auth_request_receives_invalid_code_shows_form()
    {
        $this->post('login', [
            'email'    => 'foo@test.com',
            'password' => '12345678',
            'remember' => 'on',
            '2fa_code' => 'invalid',
        ])->assertViewIs('laraguard::auth')->assertStatus(422);
    }

    public function test_auth_request_receives_empty_code_shows_form()
    {
        $this->post('login', [
            'email'    => 'foo@test.com',
            'password' => '12345678',
            'remember' => 'on',
        ])->assertViewIs('laraguard::auth')->assertForbidden();
    }

    public function test_auth_request_receives_expired_code_shows_form()
    {
        $this->post('login', [
            'email'    => 'foo@test.com',
            'password' => '12345678',
            'remember' => 'on',
            '2fa_code' => $this->user->twoFactorAuth->makeCode('now', -2),
        ])->assertViewIs('laraguard::auth')->assertStatus(422);
    }

    public function test_auth_request_receives_valid_code_succeeds()
    {
        $this->post('login', [
            'email'    => 'foo@test.com',
            'password' => '12345678',
            'remember' => 'on',
            '2fa_code' => $this->user->twoFactorAuth->makeCode(),
        ])->assertSee('authenticated')->assertOk();
    }

    public function test_auth_request_receives_recovery_code_without_recovery_enabled_shows_form()
    {
        $code = $this->user->generateRecoveryCodes()->first()['code'];

        config(['laraguard.recovery.enabled' => false]);

        $this->post('login', [
            'email'    => 'foo@test.com',
            'password' => '12345678',
            'remember' => 'on',
            '2fa_code' => $code,
        ])->assertViewIs('laraguard::auth')->assertStatus(422);
    }

    public function test_auth_request_receives_recovery_code_without_recovery_codes_available_shows_form()
    {
        $code = $this->user->generateRecoveryCodes()->first()['code'];

        $this->user->twoFactorAuth->setAttribute('recovery_codes', null)->save();

        $this->post('login', [
            'email'    => 'foo@test.com',
            'password' => '12345678',
            'remember' => 'on',
            '2fa_code' => $code,
        ])->assertViewIs('laraguard::auth')->assertStatus(422);
    }

    public function test_auth_request_receives_recovery_code_succeeds()
    {
        $code = $this->user->generateRecoveryCodes()->first()['code'];

        $this->post('login', [
            'email'    => 'foo@test.com',
            'password' => '12345678',
            'remember' => 'on',
            '2fa_code' => $code,
        ])->assertSee('authenticated')->assertOk();
    }

    public function test_auth_request_receives_code_and_doesnt_saves_device()
    {
        Carbon::setTestNow();

        $code = $this->user->twoFactorAuth->makeCode();

        $this->post('login', [
            'email'    => 'foo@test.com',
            'password' => '12345678',
            'remember' => 'on',
            '2fa_code' => $code,
        ])->assertSee('authenticated')->assertOk();

        $this->user->refresh();

        $this->assertNull($this->user->twoFactorAuth->safe_devices);
    }

    public function test_auth_requests_receives_code_and_saves_device()
    {
        config(['laraguard.safe_devices.enabled' => true]);

        Carbon::setTestNow();

        $code = $this->user->twoFactorAuth->makeCode();

        $this->post('login', [
            'email'    => 'foo@test.com',
            'password' => '12345678',
            'remember' => 'on',
            '2fa_code' => $code,
        ])->assertSee('authenticated')->assertOk();

        $this->user->refresh();

        $this->assertCount(1, $this->user->twoFactorAuth->safe_devices);

        $code = $this->user->generateRecoveryCodes()->first()['code'];

        $this->post('login', [
            'email'    => 'foo@test.com',
            'password' => '12345678',
            'remember' => 'on',
            '2fa_code' => $code,
        ])->assertSee('authenticated')->assertOk();

        $this->user->refresh();

        $this->assertCount(2, $this->user->twoFactorAuth->safe_devices);
    }
}
