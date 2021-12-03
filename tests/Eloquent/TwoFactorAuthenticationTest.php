<?php

namespace Tests\Eloquent;

use Carbon\Carbon;
use Tests\RegistersPackage;
use Orchestra\Testbench\TestCase;
use ParagonIE\ConstantTime\Base32;
use Tests\Stubs\UserStub;
use Tests\Stubs\UserTwoFactorStub;
use Tests\RunsPublishableMigrations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use DarkGhostHunter\Laraguard\Eloquent\TwoFactorAuthentication;

class TwoFactorAuthenticationTest extends TestCase
{
    use RegistersPackage;
    use DatabaseMigrations;
    use RunsPublishableMigrations;

    protected $tfa;

    protected function setUp() : void
    {
        $this->afterApplicationCreated([$this, 'loadLaravelMigrations']);
        $this->afterApplicationCreated([$this, 'runPublishableMigration']);
        parent::setUp();
    }

    public function test_returns_authenticatable(): void
    {
        $user = UserTwoFactorStub::create([
            'name'     => 'foo',
            'email'    => 'foo@test.com',
            'password' => UserStub::PASSWORD_SECRET,
        ]);

        $user->twoFactorAuth()->save(
            $tfa = TwoFactorAuthentication::factory()->make()
        );

        $this->assertInstanceOf(MorphTo::class, $tfa->authenticatable());
        $this->assertTrue($user->is($tfa->authenticatable));
    }


    public function test_lowercases_algorithm(): void
    {
        $tfa = TwoFactorAuthentication::factory()
            ->withRecovery()->withSafeDevices()
            ->make([
                'algorithm' => 'AbCdE2',
            ]);

        $this->assertEquals('abcde2', $tfa->algorithm);
    }

    public function test_is_enabled_and_is_disabled(): void
    {
        $tfa = new TwoFactorAuthentication();

        $this->assertTrue($tfa->isDisabled());
        $this->assertFalse($tfa->isEnabled());

        $tfa->enabled_at = now();

        $this->assertTrue($tfa->isEnabled());
        $this->assertFalse($tfa->isDisabled());
    }

    public function test_flushes_authentication(): void
    {
        $tfa = TwoFactorAuthentication::factory()
            ->withRecovery()->withSafeDevices()
            ->create([
                'authenticatable_type' => 'test',
                'authenticatable_id'   => 9,
            ]);

        $this->assertNotNull($old = $tfa->shared_secret);
        $this->assertNotNull($tfa->enabled_at);
        $this->assertNotNull($label = $tfa->label);
        $this->assertNotNull($tfa->digits);
        $this->assertNotNull($tfa->seconds);
        $this->assertNotNull($tfa->window);
        $this->assertNotNull($tfa->algorithm);
        $this->assertNotNull($tfa->recovery_codes_generated_at);
        $this->assertNotNull($tfa->safe_devices);

        $tfa->flushAuth()->save();

        $this->assertNotEquals($old, $tfa->shared_secret);
        $this->assertNull($tfa->enabled_at);
        $this->assertNotNull($tfa->label);
        $this->assertEquals($label, $tfa->label);
        $this->assertEquals(config('laraguard.totp.digits'), $tfa->digits);
        $this->assertEquals(config('laraguard.totp.seconds'), $tfa->seconds);
        $this->assertEquals(config('laraguard.totp.window'), $tfa->window);
        $this->assertEquals(config('laraguard.totp.algorithm'), $tfa->algorithm);
        $this->assertNull($tfa->recovery_codes_generated_at);
        $this->assertNull($tfa->safe_devices);
    }

    public function test_generates_random_secret(): void
    {
        $secret = TwoFactorAuthentication::generateRandomSecret();

        $this->assertEquals(config('laraguard.secret_length'), strlen(Base32::decodeUpper($secret)));
    }

    public function test_makes_code(): void
    {
        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->make([
            'shared_secret' => $secret = 'KS72XBTN5PEBGX2IWBMVW44LXHPAQ7L3',
        ]);

        Carbon::setTestNow(Carbon::create(2020, 1, 1, 20, 29, 59));
        $this->assertEquals('779186', $tfa->makeCode());
        $this->assertEquals('716347', $tfa->makeCode('now', 1));

        Carbon::setTestNow(Carbon::create(2020, 1, 1, 20, 30, 0));
        $this->assertEquals('716347', $tfa->makeCode());
        $this->assertEquals('779186', $tfa->makeCode('now', -1));

        for ($i = 0 ; $i < 30 ; ++$i) {
            Carbon::setTestNow(Carbon::create(2020, 1, 1, 20, 30, $i));
            $this->assertEquals('716347', $tfa->makeCode());
        }

        Carbon::setTestNow(Carbon::create(2020, 1, 1, 20, 30, 31));
        $this->assertEquals('133346', $tfa->makeCode());

        $this->assertEquals('818740', $tfa->makeCode(
            Carbon::create(2020, 1, 1, 1, 1, 1))
        );

        $this->assertEquals('976814', $tfa->makeCode('4th february 2020'));
    }

    public function test_makes_code_for_timestamp(): void
    {
        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->make([
            'shared_secret' => $secret = 'KS72XBTN5PEBGX2IWBMVW44LXHPAQ7L3',
        ]);

        $this->assertEquals('566278', $tfa->makeCode(1581300000));
        $this->assertTrue($tfa->validateCode('566278', 1581300000));
    }

    public function test_validate_code(): void
    {
        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->make([
            'shared_secret' => $secret = 'KS72XBTN5PEBGX2IWBMVW44LXHPAQ7L3',
            'window'        => 0,
        ]);

        Carbon::setTestNow($time = Carbon::create(2020, 1, 1, 20, 30, 0));

        $this->assertEquals('716347', $code = $tfa->makeCode());
        $this->assertTrue($tfa->validateCode($tfa->makeCode()));

        Carbon::setTestNow($time = Carbon::create(2020, 1, 1, 20, 29, 59));
        $this->assertFalse($tfa->validateCode($code));

        Carbon::setTestNow($time = Carbon::create(2020, 1, 1, 20, 30, 31));
        $this->assertFalse($tfa->validateCode($code));
    }

    public function test_validate_code_with_window(): void
    {
        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->make([
            'shared_secret' => $secret = 'KS72XBTN5PEBGX2IWBMVW44LXHPAQ7L3',
            'window'        => 1,
        ]);

        Carbon::setTestNow($time = Carbon::create(2020, 1, 1, 20, 30, 0));

        $this->assertEquals('716347', $code = $tfa->makeCode());
        $this->assertTrue($tfa->validateCode($tfa->makeCode()));

        Cache::getStore()->flush();
        Carbon::setTestNow($time = Carbon::create(2020, 1, 1, 20, 29, 59));
        $this->assertFalse($tfa->validateCode($code));

        Cache::getStore()->flush();
        Carbon::setTestNow($time = Carbon::create(2020, 1, 1, 20, 30, 31));
        $this->assertTrue($tfa->validateCode($code));

        Cache::getStore()->flush();
        Carbon::setTestNow($time = Carbon::create(2020, 1, 1, 20, 30, 59));
        $this->assertTrue($tfa->validateCode($code));

        Cache::getStore()->flush();
        Carbon::setTestNow($time = Carbon::create(2020, 1, 1, 20, 31, 0));
        $this->assertFalse($tfa->validateCode($code));
    }

    public function test_contains_unused_recovery_codes(): void
    {
//        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->makeOne();
//
//        $this->assertTrue($tfa->containsUnusedRecoveryCodes());

        TwoFactorAuthentication::make()
            ->forceFill([
                'recovery_codes' => null
            ]);

        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->makeOne([
            'recovery_codes' => null,
        ]);

        $this->assertFalse($tfa->containsUnusedRecoveryCodes());

        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->makeOne([
            'recovery_codes' => collect([
                [
                    'code'    => '2G5oP36',
                    'used_at' => 'anything not null',
                ],
            ]),
        ]);

        $this->assertFalse($tfa->containsUnusedRecoveryCodes());
    }

    public function test_generates_recovery_codes(): void
    {
        $codes = TwoFactorAuthentication::generateRecoveryCodes(13, 7);

        $this->assertCount(13, $codes);

        $codes->each(function ($item) {
            $this->assertEquals(7, strlen($item['code']));
            $this->assertNull($item['used_at']);
        });
    }

    public function test_generates_random_safe_device_remember_token(): void
    {
        $this->assertEquals(100, strlen((new TwoFactorAuthentication)->generateSafeDeviceToken()));
    }

    public function test_serializes_to_string(): void
    {
        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->make([
            'shared_secret' => $secret = 'KS72XBTN5PEBGX2IWBMVW44LXHPAQ7L3',
        ]);

        $this->assertEquals($secret, $tfa->toString());
        $this->assertEquals($secret, $tfa->__toString());
        $this->assertEquals($secret, (string)$tfa);
    }

    public function test_serializes_to_grouped_string(): void
    {
        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->make([
            'shared_secret' => 'KS72XBTN5PEBGX2IWBMVW44LXHPAQ7L3',
        ]);

        $this->assertEquals('KS72 XBTN 5PEB GX2I WBMV W44L XHPA Q7L3', $tfa->toGroupedString());
    }

    public function test_serializes_to_uri(): void
    {
        config(['laraguard.issuer' => 'quz']);

        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->make([
            'label'         => 'test@foo.com',
            'shared_secret' => 'KS72XBTN5PEBGX2IWBMVW44LXHPAQ7L3',
            'algorithm'     => 'sHa256',
            'digits'        => 14,
        ]);

        $uri = 'otpauth://totp/quz%3Atest@foo.com?issuer=quz&label=test%40foo.com&secret=KS72XBTN5PEBGX2IWBMVW44LXHPAQ7L3&algorithm=SHA256&digits=14';

        $this->assertEquals($uri, $tfa->toUri());
    }

    public function test_serializes_to_qr_and_renders_to_qr(): void
    {
        config(['laraguard.issuer' => 'quz']);

        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->make([
            'label'         => 'test@foo.com',
            'shared_secret' => 'KS72XBTN5PEBGX2IWBMVW44LXHPAQ7L3',
            'algorithm'     => 'sHa256',
            'digits'        => 14,
        ]);

        $this->assertStringEqualsFile(__DIR__ . '/../Stubs/QrStub.svg', $tfa->toQr());
        $this->assertStringEqualsFile(__DIR__ . '/../Stubs/QrStub.svg', $tfa->render());
    }

    public function test_serializes_to_qr_and_renders_to_qr_with_custom_values(): void
    {
        config(['laraguard.issuer' => 'quz']);
        config(['laraguard.qr_code' => [
            'size' => 600,
            'margin' => 10
        ]]);

        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->make([
            'label'         => 'test@foo.com',
            'shared_secret' => 'KS72XBTN5PEBGX2IWBMVW44LXHPAQ7L3',
            'algorithm'     => 'sHa256',
            'digits'        => 14,
        ]);

        $this->assertStringEqualsFile(__DIR__ . '/../Stubs/CustomQrStub.svg', $tfa->toQr());
        $this->assertStringEqualsFile(__DIR__ . '/../Stubs/CustomQrStub.svg', $tfa->render());
    }

    public function test_serializes_uri_to_json(): void
    {
        config(['laraguard.issuer' => 'quz']);

        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->make([
            'label'         => 'test@foo.com',
            'shared_secret' => 'KS72XBTN5PEBGX2IWBMVW44LXHPAQ7L3',
            'algorithm'     => 'sHa256',
            'digits'        => 14,
        ]);

        $uri = '"otpauth:\/\/totp\/quz%3Atest@foo.com?issuer=quz&label=test%40foo.com&secret=KS72XBTN5PEBGX2IWBMVW44LXHPAQ7L3&algorithm=SHA256&digits=14"';

        $this->assertJson($tfa->toJson());
        $this->assertEquals($uri, $tfa->toJson());
        $this->assertEquals($uri, json_encode($tfa));
    }

    public function test_changes_issuer(): void
    {
        config(['laraguard.issuer' => 'foo bar']);

        $tfa = TwoFactorAuthentication::factory()->withRecovery()->withSafeDevices()->make([
            'label'         => 'test@foo.com',
            'shared_secret' => 'KS72XBTN5PEBGX2IWBMVW44LXHPAQ7L3',
            'algorithm'     => 'sHa256',
            'digits'        => 14,
        ]);

        $uri = '"otpauth:\/\/totp\/foo%20bar%3Atest@foo.com?issuer=foo%20bar&label=test%40foo.com&secret=KS72XBTN5PEBGX2IWBMVW44LXHPAQ7L3&algorithm=SHA256&digits=14"';

        $this->assertJson($tfa->toJson());
        $this->assertEquals($uri, $tfa->toJson());
        $this->assertEquals($uri, json_encode($tfa));
    }
}
