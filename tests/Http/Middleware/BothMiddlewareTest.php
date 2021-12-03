<?php

namespace Tests\Http\Middleware;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;
use Tests\CreatesTwoFactorUser;
use Tests\RegistersPackage;
use Tests\RunsPublishableMigrations;

class BothMiddlewareTest extends TestCase
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
            $this->app['router']->get('intended', function () {
                return 'ok';
            })->middleware('web', 'auth', '2fa.enabled', '2fa.confirm');

            $this->app['router']->get('notice', function () {
                return '2fa.notice';
            })->middleware('web', 'auth')->name('2fa.notice');
        });

        parent::setUp();
    }

    public function test_enforces_user_to_activate_2fa()
    {
        $this->user->disableTwoFactorAuth();

        $this->actingAs($this->user);

        $this->get('intended')->assertRedirect('notice');
    }

    public function test_enforces_user_to_confirm_2fa()
    {
        $this->actingAs($this->user);

        $this->get('intended')->assertRedirect('2fa/confirm');
    }

    public function test_doesnt_skip_confirm_if_safe_device()
    {
        $this->actingAs($this->user);

        $this->user->twoFactorAuth->safe_devices = collect()->push([
            '2fa_remember' => $token = 'secret_token',
            'ip'           => '127.0.0.1',
            'added_at'     => now()->getTimestamp(),
        ]);

        $this->withCookie('_2fa_remember', $token)
            ->get('intended')
            ->assertRedirect('2fa/confirm');
    }
}

