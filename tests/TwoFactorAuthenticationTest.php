<?php

namespace Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Orchestra\Testbench\TestCase;
use Tests\Stubs\UserTwoFactorStub;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use DarkGhostHunter\Laraguard\Events\TwoFactorEnabled;
use DarkGhostHunter\Laraguard\Events\TwoFactorDisabled;
use DarkGhostHunter\Laraguard\Eloquent\TwoFactorAuthentication;
use DarkGhostHunter\Laraguard\Events\TwoFactorRecoveryCodesDepleted;
use DarkGhostHunter\Laraguard\Events\TwoFactorRecoveryCodesGenerated;

class TwoFactorAuthenticationTest extends TestCase
{
    use DatabaseMigrations;
    use RegistersPackage;
    use CreatesTwoFactorUser;
    use RegistersLoginRoute;
    use WithFaker;

    protected function setUp() : void
    {
        $this->afterApplicationCreated([$this, 'loadLaravelMigrations']);
        $this->afterApplicationCreated([$this, 'registerLoginRoute']);
        $this->afterApplicationCreated([$this, 'createTwoFactorUser']);
        parent::setUp();
    }

    public function test_hides_relation_from_serialization()
    {
        $array = $this->user->toArray();

        $this->assertArrayNotHasKey('two_factor_auth', $array);
        $this->assertArrayNotHasKey('twoFactorAuth', $array);
    }

    public function test_returns_two_factor_relation()
    {
        $this->assertInstanceOf(MorphOne::class, $this->user->twoFactorAuth());
        $this->assertInstanceOf(TwoFactorAuthentication::class, $this->user->twoFactorAuth);
    }

    public function test_has_two_factor_enabled()
    {
        $this->assertTrue($this->user->hasTwoFactorEnabled());

        $this->user->disableTwoFactorAuth();

        $this->assertFalse($this->user->hasTwoFactorEnabled());
    }

    public function test_disables_two_factor_authentication()
    {
        $events = Event::fake();

        $this->user->disableTwoFactorAuth();
        $this->assertFalse($this->user->hasTwoFactorEnabled());

        $events->assertDispatched(TwoFactorDisabled::class, function ($event) {
            return $this->user->is($event->user);
        });
    }

    public function test_enables_two_factor_authentication()
    {
        $events = Event::fake();

        $this->user->enableTwoFactorAuth();
        $this->assertTrue($this->user->hasTwoFactorEnabled());

        $events->assertDispatched(TwoFactorEnabled::class, function ($event) {
            return $this->user->is($event->user);
        });
    }

    public function test_creates_two_factor_authentication()
    {
        $events = Event::fake();
        $user = UserTwoFactorStub::create([
            'name'     => 'bar',
            'email'    => 'bar@test.com',
            'password' => '$2y$10$EicEv29xyMt/AbuWc0AIkeWb8Ip0fdhAYqgiXUaoG8Klu43521jQW',
        ]);

        $this->assertDatabaseMissing('two_factor_authentications', [
            ['authenticatable_type', UserTwoFactorStub::class],
            ['authenticatable_id', $user->getKey()],
        ]);

        $tfa = $user->createTwoFactorAuth();

        $this->assertInstanceOf(TwoFactorAuthentication::class, $tfa);
        $this->assertTrue($tfa->exists);
        $this->assertFalse($user->hasTwoFactorEnabled());

        $this->assertDatabaseHas('two_factor_authentications', [
            ['authenticatable_type', UserTwoFactorStub::class],
            ['authenticatable_id', $user->getKey()],
            ['enabled_at', null],
        ]);

        $events->assertNotDispatched(TwoFactorEnabled::class);
    }

    public function test_creates_two_factor_flushes_old_auth()
    {
        $this->user->twoFactorAuth->safe_devices = collect([1, 2, 3]);
        $this->user->twoFactorAuth->save();

        $this->assertNotEmpty($this->user->getRecoveryCodes());
        $this->assertNotNull($this->user->twoFactorAuth->recovery_codes_generated_at);
        $this->assertNotEmpty($this->user->safeDevices());
        $this->assertNotNull($this->user->twoFactorAuth->enabled_at);

        $this->user->createTwoFactorAuth();

        $this->assertEmpty($this->user->getRecoveryCodes());
        $this->assertNull($this->user->twoFactorAuth->recovery_codes_generated_at);
        $this->assertEmpty($this->user->safeDevices());
        $this->assertNull($this->user->twoFactorAuth->enabled_at);
    }

    public function test_rewrites_when_creating_two_factor_authentication()
    {
        $this->assertDatabaseHas('two_factor_authentications', [
            ['authenticatable_type', UserTwoFactorStub::class],
            ['authenticatable_id', $this->user->getKey()],
            ['enabled_at', '!=', null],
        ]);

        $this->assertTrue($this->user->hasTwoFactorEnabled());

        $old = $this->user->twoFactorAuth->shared_secret;

        $this->user->createTwoFactorAuth();

        $this->assertFalse($this->user->hasTwoFactorEnabled());
        $this->assertNotEquals($old, $this->user->twoFactorAuth->shared_secret);
    }

    public function test_new_user_confirms_two_factor_successfully()
    {
        $event = Event::fake();

        Carbon::setTestNow($now = Carbon::create(2020, 01, 01, 18, 30));

        $user = UserTwoFactorStub::create([
            'name'     => 'bar',
            'email'    => 'bar@test.com',
            'password' => '$2y$10$EicEv29xyMt/AbuWc0AIkeWb8Ip0fdhAYqgiXUaoG8Klu43521jQW',
        ]);

        $user->createTwoFactorAuth();

        $code = $user->makeTwoFactorCode();

        $this->assertTrue($user->confirmTwoFactorAuth($code));
        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertFalse($user->validateTwoFactorCode($code));

        Cache::getStore()->flush();
        $this->assertTrue($user->validateTwoFactorCode($code));

        $this->assertEquals($now, $user->twoFactorAuth->enabled_at);

        $event->assertDispatched(TwoFactorRecoveryCodesGenerated::class, function ($event) use ($user) {
            return $user->is($event->user);
        });
    }

    public function test_confirms_twice_but_doesnt_change_the_secret()
    {
        $event = Event::fake();

        $old_now = $this->user->twoFactorAuth->enabled_at;

        Carbon::setTestNow(Carbon::create(2020, 01, 01, 18, 30));

        $secret = $this->user->twoFactorAuth->shared_secret;

        $code = $this->user->makeTwoFactorCode();

        $this->assertTrue($this->user->confirmTwoFactorAuth($code));

        $this->user->refresh();

        $this->assertTrue($this->user->hasTwoFactorEnabled());
        $this->assertTrue($this->user->validateTwoFactorCode($code));
        $this->assertEquals($old_now, $this->user->twoFactorAuth->enabled_at);
        $this->assertEquals($secret, $this->user->twoFactorAuth->shared_secret);

        $event->assertNotDispatched(TwoFactorRecoveryCodesGenerated::class);
    }

    public function test_doesnt_confirm_two_factor_auth_with_old_recovery_code()
    {
        $recovery_code = $this->user->twoFactorAuth->recovery_codes->random();

        $code = $recovery_code['code'];

        $this->user->createTwoFactorAuth();

        $this->assertFalse($this->user->confirmTwoFactorAuth($code));
    }

    public function test_old_user_confirms_new_two_factor_successfully()
    {
        $event = Event::fake();

        Carbon::setTestNow($now = Carbon::create(2020, 01, 01, 18, 30));

        $old_code = $this->user->makeTwoFactorCode();

        $this->assertTrue($this->user->validateTwoFactorCode($old_code));

        $this->user->createTwoFactorAuth();

        $new_code = $this->user->makeTwoFactorCode();

        $this->assertFalse($this->user->confirmTwoFactorAuth($old_code));
        $this->assertFalse($this->user->hasTwoFactorEnabled());

        Cache::getStore()->flush();
        $this->assertTrue($this->user->confirmTwoFactorAuth($new_code));
        $this->assertTrue($this->user->hasTwoFactorEnabled());

        Cache::getStore()->flush();
        $this->assertFalse($this->user->validateTwoFactorCode($old_code));
        $this->assertTrue($this->user->validateTwoFactorCode($new_code));

        $this->assertEquals($now, $this->user->twoFactorAuth->enabled_at);
        $this->assertEquals($now, $this->user->twoFactorAuth->updated_at);

        $event->assertDispatched(TwoFactorRecoveryCodesGenerated::class, function ($event) {
            return $this->user->is($event->user);
        });
    }

    public function test_validates_two_factor_code()
    {
        Carbon::setTestNow($now = Carbon::create(2020, 01, 01, 18, 30));

        $code = $this->user->makeTwoFactorCode();

        $this->assertTrue($this->user->validateTwoFactorCode($code));
    }

    public function test_validates_two_factor_code_with_recovery_code()
    {
        Carbon::setTestNow($now = Carbon::create(2020, 01, 01, 18, 30));

        $recovery_code = $this->user->getRecoveryCodes()->random()['code'];

        $code = $this->user->makeTwoFactorCode();

        $this->assertTrue($this->user->validateTwoFactorCode($code));

        $this->assertTrue($this->user->validateTwoFactorCode($recovery_code));
        $this->assertFalse($this->user->validateTwoFactorCode($recovery_code));
    }

    public function test_doesnt_validates_if_two_factor_auth_is_disabled()
    {
        Carbon::setTestNow($now = Carbon::create(2020, 01, 01, 18, 30));

        $recovery_code = $this->user->getRecoveryCodes()->random()['code'];

        $code = $this->user->makeTwoFactorCode();

        $this->assertTrue($this->user->validateTwoFactorCode($code));

        $this->user->disableTwoFactorAuth();

        $this->assertFalse($this->user->validateTwoFactorCode($code));
        $this->assertFalse($this->user->validateTwoFactorCode($recovery_code));
    }

    public function test_fires_recovery_codes_depleted()
    {
        $event = Event::fake();

        foreach ($this->user->getRecoveryCodes() as $item) {
            $this->assertTrue($this->user->validateTwoFactorCode($item['code']));
        }

        foreach ($this->user->getRecoveryCodes() as $item) {
            $this->assertFalse($this->user->validateTwoFactorCode($item['code']));
        }

        $event->assertDispatchedTimes(TwoFactorRecoveryCodesDepleted::class, 1);
        $event->assertDispatched(TwoFactorRecoveryCodesDepleted::class, function ($event) {
            return $this->user->is($event->user);
        });
    }

    public function test_safe_device()
    {
        Carbon::setTestNow($now = Carbon::create(2020, 01, 01, 18, 30));

        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => $ip = $this->faker->ipv4,
        ]);

        $this->assertEmpty($this->user->safeDevices());

        $this->user->addSafeDevice($request);

        $this->assertCount(1, $this->user->safeDevices());
        $this->assertEquals($ip, $this->user->safeDevices()->first()['ip']);
        $this->assertEquals(1577903400, $this->user->safeDevices()->first()['added_at']);
    }

    public function test_oldest_safe_device_discarded_when_adding_maximum()
    {
        Carbon::setTestNow(Carbon::create(2020, 01, 01, 18));

        $this->user->addSafeDevice(
            Request::create('/', 'GET', [], [], [], [
                'REMOTE_ADDR' => $old_request_ip = $this->faker->ipv4,
            ])
        );

        $this->assertTrue($this->user->safeDevices()->contains('ip', $old_request_ip));

        $max_devices = config('laraguard.safe_devices.max_devices');

        for ($i = 0 ; $i < $max_devices ; ++$i) {
            Carbon::setTestNow(Carbon::create(2020, 01, 01, 18, 30, $i));

            $this->user->addSafeDevice(
                Request::create('/', 'GET', [], [], [], [
                    'REMOTE_ADDR' => $this->faker->ipv4,
                ])
            );
        }

        $this->assertCount(3, $this->user->safeDevices());

        $this->assertFalse($this->user->safeDevices()->contains('ip', $old_request_ip));
    }

    public function test_flushes_safe_devices()
    {
        $max_devices = config('laraguard.safe_devices.max_devices') + 4;

        for ($i = 0 ; $i < $max_devices ; ++$i) {
            Carbon::setTestNow(Carbon::create(2020, 01, 01, 18, 30, $i));

            $this->user->addSafeDevice(
                Request::create('/', 'GET', [], [], [], [
                    'REMOTE_ADDR' => $this->faker->ipv4,
                ])
            );
        }

        $this->assertCount(3, $this->user->safeDevices());

        $this->user->flushSafeDevices();

        $this->assertEmpty($this->user->safeDevices());
    }

    public function test_is_safe_device_and_safe_with_other_ip()
    {
        $max_devices = config('laraguard.safe_devices.max_devices');

        for ($i = 0 ; $i < $max_devices ; ++$i) {
            Carbon::setTestNow(Carbon::create(2020, 01, 01, 18, 30, $i));

            $this->user->addSafeDevice(
                Request::create('/', 'GET', [], [], [], [
                    'REMOTE_ADDR' => $this->faker->ipv4,
                ])
            );
        }

        $request = Request::create('/', 'GET', [], [
            '2fa_remember' => $this->user->safeDevices()->random()['2fa_remember'],
        ], [], [
            'REMOTE_ADDR' => $this->faker->ipv4,
        ]);

        $this->assertTrue($this->user->isSafeDevice($request));
        $this->assertFalse($this->user->isNotSafeDevice($request));
    }

    public function test_not_safe_device_if_remember_code_doesnt_match()
    {
        $max_devices = config('laraguard.safe_devices.max_devices');

        for ($i = 0 ; $i < $max_devices ; ++$i) {
            Carbon::setTestNow($now = Carbon::create(2020, 01, 01, 18, 30, $i));

            $this->user->addSafeDevice(
                Request::create('/', 'GET', [], [], [], [
                    'REMOTE_ADDR' => $ip = $this->faker->ipv4,
                ])
            );
        }

        $request = Request::create('/', 'GET', [], [
            '2fa_remember' => 'anything',
        ], [], [
            'REMOTE_ADDR' => $ip,
        ]);

        $this->assertFalse($this->user->isSafeDevice($request));
        $this->assertTrue($this->user->isNotSafeDevice($request));
    }

    public function test_not_safe_device_if_expired()
    {
        $max_devices = config('laraguard.safe_devices.max_devices');

        Carbon::setTestNow($now = Carbon::create(2020, 01, 01, 18, 30));

        for ($i = 0 ; $i < $max_devices ; ++$i) {
            $this->user->addSafeDevice(
                Request::create('/', 'GET', [], [], [], [
                    'REMOTE_ADDR' => $this->faker->ipv4,
                ])
            );
        }

        $request = Request::create('/', 'GET', [], [
            '2fa_remember' => $this->user->safeDevices()->random()['2fa_remember'],
        ], [], [
            'REMOTE_ADDR' => $this->faker->ipv4,
        ]);

        $this->assertTrue($this->user->isSafeDevice($request));
        $this->assertFalse($this->user->isNotSafeDevice($request));

        Carbon::setTestNow($now->clone()->addDays(config('laraguard.safe_devices.expiration_days'))->subSecond());

        $this->assertTrue($this->user->isSafeDevice($request));
        $this->assertFalse($this->user->isNotSafeDevice($request));

        Carbon::setTestNow($now->clone()->addDays(config('laraguard.safe_devices.expiration_days'))->addSecond());

        $this->assertTrue($this->user->isNotSafeDevice($request));
        $this->assertFalse($this->user->isSafeDevice($request));
    }

    public function test_unique_code_works_only_one_time()
    {
        config(['laraguard.unique' => true]);

        Carbon::setTestNow($now = Carbon::create(2020, 01, 01, 18, 30, 0));

        $code = $this->user->makeTwoFactorCode();

        $this->assertTrue($this->user->validateTwoFactorCode($code));
        $this->assertFalse($this->user->validateTwoFactorCode($code));

        Carbon::setTestNow($now = Carbon::create(2020, 01, 01, 18, 30, 59));

        $new_code = $this->user->makeTwoFactorCode();
        $this->assertTrue($this->user->validateTwoFactorCode($new_code));
        $this->assertFalse($this->user->validateTwoFactorCode($code));
    }

    public function test_unique_code_works_only_one_time_in_extended_time()
    {
        config(['laraguard.unique' => true]);

        Carbon::setTestNow($now = Carbon::create(2020, 01, 01, 18, 30, 20));

        $code = $this->user->makeTwoFactorCode();

        Carbon::setTestNow($now = Carbon::create(2020, 01, 01, 18, 30, 59));

        $this->assertTrue($this->user->validateTwoFactorCode($code));
        $this->assertFalse($this->user->validateTwoFactorCode($code));
    }
}
