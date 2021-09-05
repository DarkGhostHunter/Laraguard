<?php

namespace Tests\Events;

use Tests\RunsPublishableMigrations;
use Tests\RegistersPackage;
use Illuminate\Support\Str;
use Tests\CreatesTwoFactorUser;
use Orchestra\Testbench\TestCase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use DarkGhostHunter\Laraguard\Events\TwoFactorEnabled;
use DarkGhostHunter\Laraguard\Events\TwoFactorDisabled;
use DarkGhostHunter\Laraguard\Events\TwoFactorRecoveryCodesDepleted;
use DarkGhostHunter\Laraguard\Events\TwoFactorRecoveryCodesGenerated;

class EventsTest extends TestCase
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

        parent::setUp();
    }

    public function test_fires_two_factor_enabled_event(): void
    {
        $event = Event::fake();

        $this->user->disableTwoFactorAuth();

        $this->user->enableTwoFactorAuth();

        $event->assertDispatched(TwoFactorEnabled::class, function (TwoFactorEnabled $event) {
            return $this->user->is($event->user);
        });
    }

    public function test_fires_two_factor_disabled_event(): void
    {
        $event = Event::fake();

        $this->user->disableTwoFactorAuth();

        $event->assertDispatched(TwoFactorDisabled::class, function (TwoFactorDisabled $event) {
            return $this->user->is($event->user);
        });
    }

    public function test_fires_two_factor_recovery_codes_depleted(): void
    {
        $event = Event::fake();

        $code = Str::random(8);

        $this->user->twoFactorAuth->recovery_codes = Collection::times(1, function () use ($code) {
            return [
                'code'    => $code,
                'used_at' => null,
            ];
        });

        $this->user->twoFactorAuth->save();

        $this->user->validateTwoFactorCode($code);

        $event->assertDispatched(TwoFactorRecoveryCodesDepleted::class, function (TwoFactorRecoveryCodesDepleted $event) {
            return $this->user->is($event->user);
        });
    }

    public function test_fires_two_factor_recovery_codes_generated(): void
    {
        $event = Event::fake();

        $this->user->generateRecoveryCodes();

        $event->assertDispatched(TwoFactorRecoveryCodesGenerated::class, function (TwoFactorRecoveryCodesGenerated $event) {
            return $this->user->is($event->user);
        });
    }
}
