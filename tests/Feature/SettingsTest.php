<?php

namespace EscolaLms\RevenueCatIntegration\Tests\Feature;

use EscolaLms\Auth\Database\Seeders\AuthPermissionSeeder;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\RevenueCatIntegration\EscolaLmsRevenueCatIntegrationServiceProvider;
use EscolaLms\RevenueCatIntegration\Tests\TestCase;
use EscolaLms\Settings\Database\Seeders\PermissionTableSeeder;
use EscolaLms\Settings\EscolaLmsSettingsServiceProvider;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Config;

class SettingsTest extends TestCase
{
    use CreatesUsers, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(EscolaLmsSettingsServiceProvider::class)) {
            $this->markTestSkipped('Settings package not installed');
        }

        $this->seed(PermissionTableSeeder::class);
        $this->seed(AuthPermissionSeeder::class);
        Config::set('escola_settings.use_database', true);
    }

    public function testAdministrableConfigApi(): void
    {
        $user = $this->makeAdmin();

        $configKey = EscolaLmsRevenueCatIntegrationServiceProvider::CONFIG_KEY;

        $authEnabled = $this->faker->boolean;
        $authKey = $this->faker->uuid;
        $apiV1Key = $this->faker->uuid;

        $this->actingAs($user, 'api')
            ->postJson('/api/admin/config',
                [
                    'config' => [
                        [
                            'key' => "{$configKey}.api_v1_key",
                            'value' => $apiV1Key,
                        ],
                        [
                            'key' => "{$configKey}.webhooks.auth.enabled",
                            'value' => $authEnabled,
                        ],
                        [
                            'key' => "{$configKey}.webhooks.auth.key",
                            'value' => $authKey,
                        ],
                    ]
                ]
            )
            ->assertOk();

        $this->actingAs($user, 'api')->getJson('/api/admin/config')
            ->assertOk()
            ->assertJsonFragment([
                $configKey => [
                    'api_v1_key' => [
                        'full_key' => "$configKey.api_v1_key",
                        'key' => 'api_v1_key',
                        'public' => false,
                        'rules' => [
                            'nullable', 'string'
                        ],
                        'value' => $apiV1Key,
                        'readonly' => false,
                    ],
                    'webhooks' => [
                        'auth' => [
                            'enabled' => [
                                'full_key' => "$configKey.webhooks.auth.enabled",
                                'key' => 'webhooks.auth.enabled',
                                'public' => false,
                                'rules' => [
                                    'required', 'boolean'
                                ],
                                'value' => $authEnabled,
                                'readonly' => false,
                            ],
                            'key' => [
                                'full_key' => "$configKey.webhooks.auth.key",
                                'key' => 'webhooks.auth.key',
                                'public' => false,
                                'rules' => [
                                    'nullable', 'string'
                                ],
                                'value' => $authKey,
                                'readonly' => false,
                            ],
                        ],
                    ],
                ],
            ]);

        $this->getJson('/api/config')
            ->assertOk()
            ->assertJsonMissing([
                'api_v1_key' => $authEnabled,
                'webhooks.auth.enabled' => $authEnabled,
                'webhooks.auth.key' => $authKey,
            ]);
    }
}
