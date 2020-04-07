<?php

namespace Tests\Listeners;

use Tests\RunsPublishableMigrations;
use Tests\RegistersPackage;
use Tests\RegistersLoginRoute;
use Tests\CreatesTwoFactorUser;
use Orchestra\Testbench\TestCase;
use Tests\Stubs\UserTwoFactorStub;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class ListenerNotRegisteredTest extends TestCase
{
    use DatabaseMigrations;
    use RunsPublishableMigrations;
    use RegistersPackage;
    use CreatesTwoFactorUser;
    use RegistersLoginRoute;

    protected function setUp() : void
    {
        $this->afterApplicationCreated([$this, 'loadLaravelMigrations']);
        $this->afterApplicationCreated([$this, 'runPublishableMigration']);
        $this->afterApplicationCreated([$this, 'registerLoginRoute']);
        $this->afterApplicationCreated([$this, 'createTwoFactorUser']);
        parent::setUp();
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('laraguard.listener', false);
        $app['config']->set('auth.providers.users.model', UserTwoFactorStub::class);
    }

    public function test_listener_disabled_doesnt_enforces_2fa()
    {
        $this->post('login', [
            'email'    => 'foo@test.com',
            'password' => '12345678',
            'remember' => 'on',
        ])->assertOk()->assertSee('authenticated');
    }
}
