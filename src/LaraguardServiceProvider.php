<?php

namespace DarkGhostHunter\Laraguard;

use Illuminate\Routing\Router;
use Illuminate\Auth\Events\Validated;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Config\Repository;

class LaraguardServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laraguard.php', 'laraguard');
    }

    /**
     * Bootstrap the application services.
     *
     * @param  \Illuminate\Contracts\Config\Repository  $config
     * @param  \Illuminate\Routing\Router  $router
     * @param  \Illuminate\Contracts\Events\Dispatcher  $dispatcher
     * @return void
     */
    public function boot(Repository $config, Router $router, Dispatcher $dispatcher)
    {
        $this->registerListener($config, $dispatcher);
        $this->registerMiddleware($router);

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'laraguard');
        $this->loadFactoriesFrom(__DIR__ . '/../database/factories');

        if ($this->app->runningInConsole()) {
            $this->publishFiles();
        }
    }

    /**
     * Register the middleware.
     *
     * @param  \Illuminate\Routing\Router  $router
     */
    protected function registerMiddleware(Router $router)
    {
        $router->aliasMiddleware('2fa', Http\Middleware\EnsureTwoFactorEnabled::class);
    }

    /**
     * Register a listeners to tackle authentication.
     *
     * @param  \Illuminate\Contracts\Config\Repository  $config
     * @param  \Illuminate\Contracts\Events\Dispatcher  $dispatcher
     */
    protected function registerListener(Repository $config, Dispatcher $dispatcher)
    {
        if (! $listener = $config['laraguard.listener']) {
            return;
        }

        $this->app->singleton(Contracts\TwoFactorListener::class, function ($app) use ($listener) {
            return new $listener($app['config'], $app['request']);
        });

        $dispatcher->listen(Attempting::class, Contracts\TwoFactorListener::class . '@saveCredentials');
        $dispatcher->listen(Validated::class, Contracts\TwoFactorListener::class . '@checkTwoFactor');
    }

    /**
     * Publish config, view and migrations files.
     *
     * @return void
     */
    protected function publishFiles()
    {
        $this->publishes([
            __DIR__ . '/../config/laraguard.php' => config_path('laraguard.php'),
        ], 'config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/laraguard'),
        ], 'views');

        // We will allow the publishing for the Two Factor Authentication migration that
        // holds the TOTP data, only if it wasn't published before, avoiding multiple
        // copies for the same migration, which can throw errors when re-migrating.
        if (! class_exists('CreateTwoFactorAuthenticationsTable')) {
            $timestamp = now()->format('Y_m_d_His');

            $this->publishes([
                __DIR__ .
                '/../database/migrations/2020_04_02_000000_create_two_factor_authentications_table.php' => database_path("/migrations/{$timestamp}_create_two_factor_authentications_table.php"),
            ], 'migrations');
        }
    }
}
