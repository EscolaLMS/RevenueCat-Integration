<?php

namespace EscolaLms\RevenueCatIntegration\Tests;

use EscolaLms\Auth\EscolaLmsAuthServiceProvider;
use EscolaLms\Auth\Models\User;
use EscolaLms\Cart\EscolaLmsCartServiceProvider;
use EscolaLms\Core\Tests\TestCase as CoreTestCase;
use EscolaLms\Payments\Providers\PaymentsServiceProvider;
use EscolaLms\RevenueCatIntegration\EscolaLmsRevenueCatIntegrationServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends CoreTestCase
{
    use DatabaseTransactions;

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            PassportServiceProvider::class,
            PermissionServiceProvider::class,
            EscolaLmsAuthServiceProvider::class,
            EscolaLmsCartServiceProvider::class,
            PaymentsServiceProvider::class,
            EscolaLmsRevenueCatIntegrationServiceProvider::class
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
    }
}
