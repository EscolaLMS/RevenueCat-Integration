<?php

namespace EscolaLms\RevenueCatIntegration\Providers;

use EscolaLms\Settings\EscolaLmsSettingsServiceProvider;
use EscolaLms\Settings\Facades\AdministrableConfig;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register()
    {
        if (class_exists(EscolaLmsSettingsServiceProvider::class)) {
            if (!$this->app->getProviders(EscolaLmsSettingsServiceProvider::class)) {
                $this->app->register(EscolaLmsSettingsServiceProvider::class);
            }

            AdministrableConfig::registerConfig('escolalms_revenuecat_integration.webhooks.auth.enabled', ['required', 'boolean'], false);
            AdministrableConfig::registerConfig('escolalms_revenuecat_integration.webhooks.auth.key', ['nullable', 'string'], false);
        }
    }
}
