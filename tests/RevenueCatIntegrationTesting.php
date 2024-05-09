<?php

namespace EscolaLms\RevenueCatIntegration\Tests;

use EscolaLms\Cart\Enums\PeriodEnum;
use EscolaLms\Cart\Models\Product;
use EscolaLms\Payments\Enums\Currency;
use Illuminate\Foundation\Testing\WithFaker;

trait RevenueCatIntegrationTesting
{
    use WithFaker;

    public function makeProductWithStoreIds(?bool $subscription = false): array
    {
        $appStoreProductId = $this->faker->uuid;
        $playStoreProductId = $this->faker->uuid;
        $product = Product::factory()
            ->when($subscription, fn($factory) => $factory->subscriptionWithoutTrial()->state(['subscription_period' => PeriodEnum::DAILY, 'subscription_duration' => 3]))
            ->state([
                'fields' => [
                    'in_app_purchase_ids' => [
                        'revenuecat' => [
                            'app_store' => $appStoreProductId,
                            'play_store' => $playStoreProductId
                        ],
                    ]
                ]
            ])
            ->create();

        return [$appStoreProductId, $playStoreProductId, $product];
    }

    public function makeWebhookPayload(array $data = []): array
    {
        $payload = [
            'api_version' => '1.0',
            'event' => [
                'aliases' => [$this->faker->uuid],
                'app_id' => $this->faker->uuid,
                'app_user_id' => $this->faker->uuid,
                'commission_percentage' => $this->faker->randomFloat(1, 0, 1),
                'country_code' => $this->faker->countryCode,
                'currency' => Currency::getRandomValue(),
                'environment' => 'SANDBOX',
                'event_timestamp_ms' => $this->faker->dateTime->getTimestamp(),
                'id' => $this->faker->uuid,
                'original_transaction_id' => $this->faker->uuid,
                'period_type' => 'NORMAL',
                'price' => $this->faker->randomFloat(3, 0, 1000),
                'price_in_purchased_currency' => $this->faker->randomFloat(2, 0, 1000),
                'product_id' => $this->faker->word,
                'purchased_at_ms' => $this->faker->dateTime->getTimestamp(),
                'store' => 'APP_STORE',
                'subscriber_attributes' => [],
                'takehome_percentage' => $this->faker->randomFloat(1, 0, 1),
                'tax_percentage' => $this->faker->randomFloat(3, 0, 1),
                'transaction_id' => $this->faker->uuid,
                'type' => 'INITIAL_PURCHASE',
            ],
        ];

        return array_merge_recursive($payload, $data);
    }

    public function makeWebhookPayloadWith(int $userId, string $eventType, string $productId, string $periodType, string $store, array $data = []): array
    {
        $payload = [
            'api_version' => '1.0',
            'event' => [
                'aliases' => [(string)$userId],
                'app_id' => $this->faker->uuid,
                'app_user_id' => (string)$userId,
                'commission_percentage' => $this->faker->randomFloat(1, 0, 1),
                'country_code' => $this->faker->countryCode,
                'currency' => Currency::getRandomValue(),
                'environment' => 'SANDBOX',
                'event_timestamp_ms' => $this->faker->dateTime->getTimestamp(),
                'id' => $this->faker->uuid,
                'original_transaction_id' => $this->faker->uuid,
                'period_type' => $periodType,
                'price' => $this->faker->randomFloat(3, 0, 1000),
                'price_in_purchased_currency' => $this->faker->randomFloat(2, 0, 1000),
                'product_id' => $productId,
                'purchased_at_ms' => $this->faker->dateTime->getTimestamp(),
                'store' => $store,
                'subscriber_attributes' => [],
                'takehome_percentage' => $this->faker->randomFloat(1, 0, 1),
                'tax_percentage' => $this->faker->randomFloat(3, 0, 1),
                'transaction_id' => $this->faker->uuid,
                'type' => $eventType,
            ],
        ];

        return array_merge_recursive($payload, $data);
    }
}
