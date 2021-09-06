<?php

namespace Tests\Http\Middleware;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Date;
use Orchestra\Testbench\TestCase;
use Tests\CreatesTwoFactorUser;
use Tests\RegistersPackage;
use Tests\RunsPublishableMigrations;
use Tests\Stubs\UserStub;

class ConfirmTwoFactorEnabledTest extends TestCase
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
            $this->app['router']->get('login', function () {
                return 'login';
            })->name('login');

            $this->app['router']->get('intended', function () {
                return 'ok';
            })->name('intended')->middleware('web', 'auth:web', '2fa.confirm');
        });

        parent::setUp();
    }

    public function test_guest_cant_access(): void
    {
        $this->assertGuest();

        $this->get('intended')->assertRedirect('login');
    }

    public function test_continues_if_user_is_not_2fa_instance(): void
    {
        $this->actingAs(UserStub::create([
            'name'     => 'test',
            'email'    => 'bar@test.com',
            'password' => UserStub::PASSWORD_SECRET,
        ]));

        $this->followingRedirects()->get('intended')->assertSee('ok');
        $this->getJson('intended')->assertSee('ok');
    }

    public function test_continues_if_user_is_2fa_but_not_activated(): void
    {
        $this->actingAs(tap($this->user)->disableTwoFactorAuth());

        $this->followingRedirects()->get('intended')->assertSee('ok');
        $this->getJson('intended')->assertSee('ok');
    }

    public function test_asks_for_confirmation(): void
    {
        $this->actingAs($this->user);

        $this->followingRedirects()->get('intended')->assertViewIs('laraguard::confirm');

        $this->getJson('intended')->assertJson(['message' => trans('laraguard::messages.required')]);
    }

    public function test_redirects_to_intended_when_code_valid(): void
    {
        $this->actingAs($this->user);

        $this->followingRedirects()
            ->get('intended')
            ->assertSessionMissing('2fa.totp_confirmed_at')
            ->assertViewIs('laraguard::confirm');

        $this->followingRedirects()
            ->post('2fa/confirm', [
                '2fa_code' => $this->user->makeTwoFactorCode(),
            ])
            ->assertSessionHas('2fa.totp_confirmed_at')
            ->assertSee('ok');

        $this->followingRedirects()
            ->get('intended')
            ->assertSee('ok');
    }

    public function test_returns_ok_on_json_response(): void
    {
        $this->actingAs($this->user);

        $this->getJson('intended')
            ->assertSessionMissing('2fa.totp_confirmed_at')
            ->assertJson(['message' => 'Two Factor Authentication is required.'])
            ->assertStatus(403);

        $this->postJson('2fa/confirm', [
            '2fa_code' => $this->user->makeTwoFactorCode(),
        ])
            ->assertSessionHas('2fa.totp_confirmed_at')
            ->assertNoContent();
    }

    public function test_returns_validation_error_when_code_invalid(): void
    {
        $this->actingAs($this->user);

        $this->followingRedirects()
            ->get('intended')
            ->assertViewIs('laraguard::confirm');

        $this->post('2fa/confirm', [
            '2fa_code' => 'invalid',
        ])
            ->assertSessionHasErrors();
    }

    public function test_bypasses_check_if_below_timeout(): void
    {
        Date::setTestNow($now = Date::create(2020, 04, 01, 20, 20));

        $this->actingAs($this->user);

        session()->put('2fa.totp_confirmed_at', $now->timestamp - config('laraguard.confirm.timeout') - 1);

        $this->followingRedirects()
            ->get('intended')
            ->assertSee('ok');

        session()->put('2fa.totp_confirmed_at', $now->timestamp - config('laraguard.confirm.timeout'));

        $this->followingRedirects()
            ->get('intended')
            ->assertViewIs('laraguard::confirm');
    }

    public function test_throttles_totp(): void
    {
        Date::setTestNow($now = Date::create(2020, 04, 01, 20, 20));

        $this->actingAs($this->user);

        $this->followingRedirects()
            ->get('intended')
            ->assertViewIs('laraguard::confirm');

        for ($i = 0; $i < 60; $i++) {
            $this->post('2fa/confirm', [
                '2fa_code' => 'invalid',
            ])->assertSessionHasErrors();
        }

        $this->post('2fa/confirm', [
            '2fa_code' => 'invalid',
        ])->assertStatus(429);
    }

    public function test_routes_to_alternate_named_route(): void
    {
        $this->app['router']->get('intended_to_foo', function () {
            return 'ok';
        })->name('intended')->middleware('web', 'auth', '2fa.confirm:foo');

        $this->app['router']->get('foo', function () {
            return 'foo';
        })->name('foo');

        $this->actingAs($this->user);

        $this->get('intended_to_foo')
            ->assertRedirect('foo');
    }
}
