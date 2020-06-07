<?php

namespace Tests\Http\Middleware;

use Tests\Stubs\UserStub;
use Tests\RegistersPackage;
use Tests\CreatesTwoFactorUser;
use Orchestra\Testbench\TestCase;
use Tests\RunsPublishableMigrations;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class RequireTwoFactorEnabledTest extends TestCase
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
            $this->app['router']->get('test', function () {
                return 'ok';
            })->middleware('2fa');
            $this->app['router']->get('notice', function () {
                return '2fa.notice';
            })->name('2fa.notice');
            $this->app['router']->get('custom', function () {
                return 'custom-notice';
            })->name('custom-notice');
        });

        parent::setUp();
    }

    public function test_guest_cant_access()
    {
        $this->followingRedirects()->get('test')->assertSee('2fa.notice');

        $this->getJson('test')->assertSee(__('Two Factor Authentication is not enabled.'))->assertForbidden();
    }

    public function test_user_no_2fa_cant_access()
    {
        $this->actingAs(UserStub::create([
            'name'     => 'test',
            'email'    => 'bar@test.com',
            'password' => '$2y$10$K0WnjWfbVBYcCvoSAh0yRurrgXgWVgQE2JHBJ.zdQdGHXgJofgGKC',
        ]));

        $this->followingRedirects()->get('test')->assertSee('2fa.notice');

        $this->getJson('test')->assertSee(__('Two Factor Authentication is not enabled.'))->assertForbidden();
    }

    public function test_user_2fa_not_enabled_cant_acesss()
    {
        $this->actingAs(tap($this->user)->disableTwoFactorAuth());

        $this->followingRedirects()->get('test')->assertSee('2fa.notice');

        $this->getJson('test')->assertSee(__('Two Factor Authentication is not enabled.'))->assertForbidden();
    }

    public function test_user_2fa_enabled_access()
    {
        $this->actingAs($this->user);

        $this->followingRedirects()->get('test')->assertSee('ok');

        $this->getJson('test')->assertSee('ok');
    }

    public function test_redirects_to_custom_notice()
    {
        $this->actingAs(tap($this->user)->disableTwoFactorAuth());

        $this->followingRedirects()->get('custom')->assertSee('custom-notice');
    }
}
