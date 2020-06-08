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
            $this->app['router']->get('login', function () {
                return 'login';
            })->name('login');
            $this->app['router']->get('test', function () {
                return 'ok';
            })->middleware('web', 'auth', '2fa.require');
            $this->app['router']->get('notice', function () {
                return '2fa.notice';
            })->middleware('web', 'auth')->name('2fa.notice');
            $this->app['router']->get('custom', function () {
                return 'custom-notice';
            })->middleware('web', 'auth')->name('custom-notice');
        });

        parent::setUp();
    }

    public function test_guest_cant_access()
    {
        $this->get('test')->assertRedirect('login');

        $this->getJson('test')->assertJson(['message' => 'Unauthenticated.'])->assertStatus(401);
    }

    public function test_user_no_2fa_can_access()
    {
        $this->actingAs(UserStub::create([
            'name'     => 'test',
            'email'    => 'bar@test.com',
            'password' => '$2y$10$K0WnjWfbVBYcCvoSAh0yRurrgXgWVgQE2JHBJ.zdQdGHXgJofgGKC',
        ]));

        $this->get('test')->assertSee('ok');

        $this->getJson('test')->assertSee('ok')->assertOk();
    }

    public function test_user_2fa_not_enabled_cant_acesss()
    {
        $this->actingAs(tap($this->user)->disableTwoFactorAuth());

        $this->followingRedirects()->get('test')->assertSee('2fa.notice');

        $this->getJson('test')
            ->assertJson(['message' => 'You need to enable Two Factor Authentication.'])
            ->assertForbidden();
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
