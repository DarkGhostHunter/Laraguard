<?php

namespace DarkGhostHunter\Laraguard;

use Illuminate\Routing\Router;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Support\ServiceProvider;
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
     * @param  \Illuminate\Foundation\Http\Kernel  $http
     * @return void
     */
    public function boot(Repository $config, Router $router, Kernel $http)
    {
        $this->registerListener($config);
        $this->registerMiddleware($router, $http);

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'laraguard');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadFactoriesFrom(__DIR__ . '/../database/factories');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/laraguard.php' => config_path('laraguard.php'),
            ], 'config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/laraguard'),
            ], 'views');
        }
    }

    /**
     * Register the middleware.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @param  \Illuminate\Foundation\Http\Kernel  $http
     */
    protected function registerMiddleware(Router $router, Kernel $http)
    {
        $router->aliasMiddleware('2fa', Http\Middleware\EnsureTwoFactorEnabled::class);

        $http->pushMiddleware(Http\Middleware\ResolveTwoFactorAuthenticatable::class);
    }

    /**
     * Register a listeners to tackle authentication.
     *
     * @param  \Illuminate\Contracts\Config\Repository  $config
     */
    protected function registerListener(Repository $config)
    {
        if (! $config['laraguard.listener']) {
            return;
        }

        // We will check if the "Validated" auth event exists. If it is, this will allow our
        // listener to not retrieve the user beforehand, since it'll be already retrieved.
        // If not, we listen to the "Attempting" to retrieve and validate it ourselves.
        $this->app['events']->listen(
            $this->getEventName(), Listeners\ForcesTwoFactorAuth::class
        );
    }

    /**
     * Checks if the "Validated" event exists, otherwise fallback to "Attempting".
     *
     * @return string
     */
    protected function getEventName()
    {
        return class_exists('Illuminate\Auth\Events\Validated')
            ? 'Illuminate\Auth\Events\Validated'
            : 'Illuminate\Auth\Events\Attempting';
    }
}
