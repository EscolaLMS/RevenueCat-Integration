<?php

namespace EscolaLms\RevenueCatIntegration;

use EscolaLms\Auth\EscolaLmsAuthServiceProvider;
use EscolaLms\RevenueCatIntegration\Providers\SettingsServiceProvider;
use EscolaLms\RevenueCatIntegration\Services\Contracts\WebhookServiceContract;
use EscolaLms\RevenueCatIntegration\Services\WebhookService;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class EscolaLmsRevenueCatIntegrationServiceProvider extends ServiceProvider
{
    const CONFIG_KEY = 'escolalms_revenuecat_integration';

    public const REPOSITORIES = [];

    public const SERVICES = [
        WebhookServiceContract::class => WebhookService::class
    ];

    public $singletons = self::SERVICES + self::REPOSITORIES;

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', self::CONFIG_KEY);

        $this->app->register(SettingsServiceProvider::class);
        $this->app->register(EscolaLmsAuthServiceProvider::class);
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    public function bootForConsole()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/config.php' => config_path(self::CONFIG_KEY . '.php'),
        ], self::CONFIG_KEY . '.config');
    }
}
