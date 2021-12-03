<?php

namespace Tests\Http\Requests;

use DarkGhostHunter\Laraguard\Http\Requests\TotpEnableRequest;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use LogicException;
use Orchestra\Testbench\TestCase;
use Tests\CreatesTwoFactorUser;
use Tests\RegistersPackage;
use Tests\RunsPublishableMigrations;

use function now;

class TotpEnableRequestTest extends TestCase
{
    use RegistersPackage;
    use DatabaseMigrations;
    use RunsPublishableMigrations;
    use CreatesTwoFactorUser;

    protected function setUp(): void
    {
        $this->afterApplicationCreated([$this, 'loadLaravelMigrations']);
        $this->afterApplicationCreated([$this, 'runPublishableMigration']);
        $this->afterApplicationCreated([$this, 'createTwoFactorUser']);

        $this->afterApplicationCreated(function () {
            $this->app['router']->post('intended', function (TotpEnableRequest $request) {
                return $request->recoveryCodes();
            })->name('intended')->middleware('web');
        });

        parent::setUp();
    }

    public function test_request_exposes_default_confirm_input_name(): void
    {
        static::assertSame('2fa_code', TotpEnableRequest::$input);
    }

    public function test_enables_2fa_if_code_correct(): void
    {
        $this->user->disableTwoFactorAuth();
        $this->user->createTwoFactorAuth();

        $this->actingAs($this->user);

        $this->travelTo(now()->addHour());

        $this->post('intended', [TotpEnableRequest::$input => $this->user->makeTwoFactorCode()])
            ->assertOk()
            ->assertExactJson($this->user->getRecoveryCodes()->toArray());

        static::assertTrue($this->user->hasTwoFactorEnabled());
    }

    public function test_doesnt_enables_2fa_if_user_is_guest(): void
    {
        $response = $this->post('intended', [TotpEnableRequest::$input => $this->user->makeTwoFactorCode()]);

        static::assertTrue($response->isServerError());
    }

    public function test_doesnt_enables_2f_if_code_incorrect(): void
    {
        $this->user->disableTwoFactorAuth();
        $this->user->createTwoFactorAuth();

        $this->actingAs($this->user);

        $this->travelTo(now()->addHour());

        $this->post('intended', [TotpEnableRequest::$input => 'invalid'])
            ->assertSessionHasErrorsIn(TotpEnableRequest::$input);

        static::assertFalse($this->user->hasTwoFactorEnabled());
    }
}
