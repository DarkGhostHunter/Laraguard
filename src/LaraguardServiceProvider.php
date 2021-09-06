<?php

namespace DarkGhostHunter\Laraguard;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class LaraguardServiceProvider extends ServiceProvider
{
    /**
     * The path of the migration file.
     *
     * @var string
     */
    protected const MIGRATION_FILE = __DIR__ . '/../database/migrations/2020_04_02_000000_create_two_factor_authentications_table.php';
    protected const UPGRADE_FILE = __DIR__ . '/../database/migrations/2020_04_02_000000_upgrade_two_factor_authentications_table.php';

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laraguard.php', 'laraguard');
    }

    /**
     * Bootstrap the application services.
     *
     * @param  \Illuminate\Contracts\Config\Repository  $config
     * @param  \Illuminate\Routing\Router  $router
     * @param  \Illuminate\Contracts\Validation\Factory  $validator
     * @return void
     */
    public function boot(Repository $config, Router $router, Factory $validator): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'laraguard');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'laraguard');

        $this->registerMiddleware($router);
        $this->registerRules($validator);
        $this->registerRoutes($config, $router);

        if ($this->app->runningInConsole()) {
            $this->publishFiles();
        }
    }

    /**
     * Register the middleware.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @return void
     */
    protected function registerMiddleware(Router $router): void
    {
        $router->aliasMiddleware('2fa.enabled', Http\Middleware\RequireTwoFactorEnabled::class);
        $router->aliasMiddleware('2fa.confirm', Http\Middleware\ConfirmTwoFactorCode::class);
    }

    /**
     * Register custom validation rules.
     *
     * @param  \Illuminate\Contracts\Validation\Factory  $validator
     * @return void
     */
    protected function registerRules(Factory $validator): void
    {
        $validator->extendImplicit('totp_code', Rules\TotpCodeRule::class, trans('laraguard::validation.totp_code'));
    }

    /**
     * Register the routes for 2FA Code confirmation.
     *
     * @param  \Illuminate\Contracts\Config\Repository  $config
     * @param  \Illuminate\Routing\Router  $router
     * @return void
     */
    protected function registerRoutes(Repository $config, Router $router): void
    {
        if ($view = $config->get('laraguard.confirm.view')) {
            $router->get('2fa/confirm', $view)->middleware('web')->name('2fa.confirm');
        }

        if ($action = $config->get('laraguard.confirm.action')) {
            $router->post('2fa/confirm', $action)->middleware('web');
        }
    }

    /**
     * Publish config, view and migrations files.
     *
     * @return void
     */
    protected function publishFiles(): void
    {
        $this->publishes([
            __DIR__ . '/../config/laraguard.php' => config_path('laraguard.php'),
        ], 'config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/laraguard'),
        ], 'views');

        $this->publishes([
            __DIR__ . '/../resources/lang' => resource_path('lang/vendor/laraguard'),
        ], 'translations');

        $this->publishes([
            self::MIGRATION_FILE => database_path('migrations/'
                . now()->format('Y_m_d_His')
                . '_create_two_factor_authentications_table.php'),
        ], 'migrations');

        $this->publishes([
            self::UPGRADE_FILE => database_path('migrations/'
                . now()->format('Y_m_d_His')
                . '_upgrade_two_factor_authentications_table.php'),
        ], 'upgrade');
    }
}
