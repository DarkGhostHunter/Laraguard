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
     * @return void
     */
    public function boot(Repository $config, Router $router, Dispatcher $dispatcher)
    {
        $this->registerListener($config, $dispatcher);
        $this->registerMiddleware($router);

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'laraguard');
        $this->loadFactoriesFrom(__DIR__ . '/../database/factories');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/laraguard.php' => config_path('laraguard.php'),
            ], 'config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/laraguard'),
            ], 'views');

            if (! class_exists('CreateTwoFactorAuthenticationsTable')) {
                $timestamp = date('Y_m_d_His', time());

                $this->publishes([
                    __DIR__.'/../database/migrations/2020_04_02_000000_create_two_factor_authentications_table.php' => database_path("/migrations/{$timestamp}_create_two_factor_authentications_table.php"),
                ], 'migrations');
            }
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
        if (! $config['laraguard.listener']) {
            return;
        }

        $this->app->singleton($config['laraguard.listener'], function ($app) use ($config) {
            return new $config['laraguard.listener']($app['config'], $app['request']);
        });
        $dispatcher->listen(Attempting::class, $config['laraguard.listener'] . '@saveCredentials');
        $dispatcher->listen(Validated::class, $config['laraguard.listener'] . '@checkTwoFactor');
    }
}
