<?php

namespace EscolaLms\RevenueCatIntegration\Tests\Api;

use EscolaLms\Cart\Enums\OrderStatus;
use EscolaLms\Cart\Enums\SubscriptionStatus;
use EscolaLms\Cart\Models\Product;
use EscolaLms\Core\Models\User;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Payments\Enums\Currency;
use EscolaLms\Payments\Enums\PaymentStatus;
use EscolaLms\RevenueCatIntegration\EscolaLmsRevenueCatIntegrationServiceProvider;
use EscolaLms\RevenueCatIntegration\Tests\RevenueCatIntegrationTesting;
use EscolaLms\RevenueCatIntegration\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

class WebhookApiTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers, WithFaker, RevenueCatIntegrationTesting;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::now()->startOfDay());
        Config::set('escola_settings.use_database', true);
    }

    public function testProcessWebhookInitialPurchaseSingleProduct(): void
    {
        $user = $this->makeStudent();
        [$appStoreProductId, $playStoreProductId, $product] = $this->makeProductWithStoreIds();
        $payload = $this->makeWebhookPayloadWith($user->getKey(), 'INITIAL_PURCHASE', $appStoreProductId, 'NORMAL', 'APP_STORE');

        $this->postJson('api/webhooks/revenuecat', $payload)
            ->assertOk();

        $this->assertProcessedWebhook(
            $product,
            $user,
            (int)(Arr::get($payload, 'event.price_in_purchased_currency') * 100),
            ['end_date' => null],
            ['tax' => (int)(Arr::get($payload, 'event.tax_percentage') * 100)],
            ['currency' => Arr::get($payload, 'event.currency')]
        );
    }

    public function testProcessWebhookNonRenewingPurchaseSingleProduct(): void
    {
        $user = $this->makeStudent();
        [$appStoreProductId, $playStoreProductId, $product] = $this->makeProductWithStoreIds();
        $payload = $this->makeWebhookPayloadWith($user->getKey(), 'NON_RENEWING_PURCHASE', $appStoreProductId, 'NORMAL', 'APP_STORE');

        $this->postJson('api/webhooks/revenuecat', $payload)
            ->assertOk();

        $this->assertProcessedWebhook(
            $product,
            $user,
            (int)(Arr::get($payload, 'event.price_in_purchased_currency') * 100),
            ['end_date' => null],
            ['tax' => (int)(Arr::get($payload, 'event.tax_percentage') * 100)],
            ['currency' => Arr::get($payload, 'event.currency')]
        );
    }

    public function testProcessWebhookInitialPurchaseSingleProductNotSupportedCurrency(): void
    {
        $user = $this->makeStudent();
        [$appStoreProductId, $playStoreProductId, $product] = $this->makeProductWithStoreIds();
        $payload = $this->makeWebhookPayloadWith($user->getKey(), 'INITIAL_PURCHASE', $appStoreProductId, 'NORMAL', 'APP_STORE');
        $payload['event']['currency'] = 'XYZ';

        $this->postJson('api/webhooks/revenuecat', $payload)
            ->assertOk();

        $this->assertProcessedWebhook(
            $product,
            $user,
            (int)(Arr::get($payload, 'event.price') * 100),
            ['end_date' => null],
            ['tax' => 0],
            ['currency' => Currency::USD]
        );
    }

    public function testProcessWebhookInitialPurchaseSubscription(): void
    {
        $user = $this->makeStudent();
        $endDate = Carbon::now()->addDays(3);
        [$appStoreProductId, $playStoreProductId, $product] = $this->makeProductWithStoreIds(true);
        $payload = $this->makeWebhookSubscriptionPayloadWith($user->getKey(), 'INITIAL_PURCHASE', $appStoreProductId, 'NORMAL', 'APP_STORE', $endDate);

        $this->postJson('api/webhooks/revenuecat', $payload)
            ->assertOk();

        $this->assertProcessedWebhook(
            $product,
            $user,
            (int)(Arr::get($payload, 'event.price_in_purchased_currency') * 100),
            ['end_date' => $endDate, 'status' => SubscriptionStatus::ACTIVE],
            ['tax' => (int)(Arr::get($payload, 'event.tax_percentage') * 100)],
            ['currency' => Arr::get($payload, 'event.currency')]
        );
    }

    public function testProcessWebhookInitialPurchaseSubscriptionPeriodFromProduct(): void
    {
        $user = $this->makeStudent();
        $endDate = Carbon::now()->addDays(3);
        [$appStoreProductId, $playStoreProductId, $product] = $this->makeProductWithStoreIds(true);
        $payload = $this->makeWebhookSubscriptionPayloadWith($user->getKey(), 'INITIAL_PURCHASE', $appStoreProductId, 'NORMAL', 'APP_STORE', $endDate);

        $this->postJson('api/webhooks/revenuecat', $payload)
            ->assertOk();

        $this->assertProcessedWebhook(
            $product,
            $user,
            (int)(Arr::get($payload, 'event.price_in_purchased_currency') * 100),
            ['end_date' => $endDate, 'status' => SubscriptionStatus::ACTIVE],
            ['tax' => (int)(Arr::get($payload, 'event.tax_percentage') * 100)],
            ['currency' => Arr::get($payload, 'event.currency')]
        );
    }

    public function testProcessWebhookInitialPurchaseSubscriptionWithTrial(): void
    {
        $user = $this->makeStudent();
        $endDate = Carbon::now()->addDays(7);
        [$appStoreProductId, $playStoreProductId, $product] = $this->makeProductWithStoreIds(true, true);
        $payload = $this->makeWebhookSubscriptionPayloadWith($user->getKey(), 'INITIAL_PURCHASE', $appStoreProductId, 'TRIAL', 'APP_STORE', $endDate);

        $this->postJson('api/webhooks/revenuecat', $payload)
            ->assertOk();

        $this->assertProcessedWebhook(
            $product,
            $user,
            (int)(Arr::get($payload, 'event.price_in_purchased_currency') * 100),
            ['end_date' => $endDate, 'status' => SubscriptionStatus::ACTIVE],
            ['status' => OrderStatus::TRIAL_PAID, 'tax' => (int)(Arr::get($payload, 'event.tax_percentage') * 100)],
            ['currency' => Arr::get($payload, 'event.currency')]
        );
    }

    public function testProcessWebhookRenewSingleProduct(): void
    {
        $user = $this->makeStudent();
        [$appStoreProductId, $playStoreProductId, $product] = $this->makeProductWithStoreIds();
        $payload = $this->makeWebhookPayloadWith($user->getKey(), 'INITIAL_PURCHASE', $appStoreProductId, 'NORMAL', 'APP_STORE');

        $this->postJson('api/webhooks/revenuecat', $payload)
            ->assertOk();

        $this->assertProcessedWebhook(
            $product,
            $user,
            (int)(Arr::get($payload, 'event.price_in_purchased_currency') * 100),
            [],
            ['tax' => (int)(Arr::get($payload, 'event.tax_percentage') * 100)],
        );

        $payload['event']['type'] = 'RENEWAL';
        $this->postJson('api/webhooks/revenuecat', $payload)
            ->assertOk();

        $this->assertProcessedWebhook(
            $product,
            $user,
            (int)(Arr::get($payload, 'event.price_in_purchased_currency') * 100),
            [],
            ['tax' => (int)(Arr::get($payload, 'event.tax_percentage') * 100)],
        );
    }

    public function testProcessWebhookRenewSubscription(): void
    {
        $user = $this->makeStudent();
        $endDate = Carbon::now()->addDays(3);
        [$appStoreProductId, $playStoreProductId, $product] = $this->makeProductWithStoreIds(true);
        $payload = $this->makeWebhookSubscriptionPayloadWith($user->getKey(), 'INITIAL_PURCHASE', $appStoreProductId, 'NORMAL', 'APP_STORE', $endDate);

        $this->postJson('api/webhooks/revenuecat', $payload)
            ->assertOk();

        $this->assertProcessedWebhook(
            $product,
            $user,
            (int)(Arr::get($payload, 'event.price_in_purchased_currency') * 100),
            ['end_date' => $endDate, 'status' => SubscriptionStatus::ACTIVE],
            ['tax' => (int)(Arr::get($payload, 'event.tax_percentage') * 100)],
        );

        $endDate = $endDate->addDays(3);
        $payload['event']['type'] = 'RENEWAL';
        $payload['event']['expiration_at_ms'] = $endDate->getTimestampMs();

        $this->postJson('api/webhooks/revenuecat', $payload)
            ->assertOk();

        $this->assertProcessedWebhook(
            $product,
            $user,
            (int)(Arr::get($payload, 'event.price_in_purchased_currency') * 100),
            ['end_date' => $endDate, 'status' => SubscriptionStatus::ACTIVE],
            ['tax' => (int)(Arr::get($payload, 'event.tax_percentage') * 100)],
        );
    }

    public function testProcessWebhookSubscriptionPaused(): void
    {
        $user = $this->makeStudent();
        $endDate = Carbon::now()->addDays(3);
        [$appStoreProductId, $playStoreProductId, $product] = $this->makeProductWithStoreIds(true);
        $payload = $this->makeWebhookSubscriptionPayloadWith($user->getKey(), 'INITIAL_PURCHASE', $appStoreProductId, 'NORMAL', 'APP_STORE', $endDate);

        $this->postJson('api/webhooks/revenuecat', $payload)
            ->assertOk();

        $pausedDate = Carbon::now()->addMinute();
        $payload['event']['type'] = 'SUBSCRIPTION_PAUSED';
        $payload['event']['expiration_at_ms'] = $pausedDate->getTimestampMs();

        $this->postJson('api/webhooks/revenuecat', $payload)
            ->assertOk();

        $this->assertProcessedWebhook(
            $product,
            $user,
            (int)(Arr::get($payload, 'event.price_in_purchased_currency') * 100),
            ['end_date' => $pausedDate, 'status' => SubscriptionStatus::ACTIVE],
            ['tax' => (int)(Arr::get($payload, 'event.tax_percentage') * 100)],
            ['currency' => Arr::get($payload, 'event.currency')]
        );

        $payload['event']['type'] = 'CANCELLATION';
        $this->postJson('api/webhooks/revenuecat', $payload)
            ->assertOk();

        $this->assertProcessedWebhook(
            $product,
            $user,
            (int)(Arr::get($payload, 'event.price_in_purchased_currency') * 100),
            ['status' => SubscriptionStatus::CANCELLED],
            ['tax' => (int)(Arr::get($payload, 'event.tax_percentage') * 100)],
        );
    }

    public function testProcessWebhookCancellationSubscription(): void
    {
        $user = $this->makeStudent();
        $endDate = Carbon::now()->addDays(3);
        [$appStoreProductId, $playStoreProductId, $product] = $this->makeProductWithStoreIds(true);
        $payload = $this->makeWebhookSubscriptionPayloadWith($user->getKey(), 'INITIAL_PURCHASE', $appStoreProductId, 'NORMAL', 'APP_STORE', $endDate);

        $this->postJson('api/webhooks/revenuecat', $payload)
            ->assertOk();

        $this->assertProcessedWebhook(
            $product,
            $user,
            (int)(Arr::get($payload, 'event.price_in_purchased_currency') * 100),
            ['end_date' => $endDate, 'status' => SubscriptionStatus::ACTIVE],
            ['tax' => (int)(Arr::get($payload, 'event.tax_percentage') * 100)],
            ['currency' => Arr::get($payload, 'event.currency')]
        );

        $payload['event']['type'] = 'CANCELLATION';
        $this->postJson('api/webhooks/revenuecat', $payload)
            ->assertOk();

        $this->assertProcessedWebhook(
            $product,
            $user,
            (int)(Arr::get($payload, 'event.price_in_purchased_currency') * 100),
            ['end_date' => $endDate, 'status' => SubscriptionStatus::CANCELLED],
            ['tax' => (int)(Arr::get($payload, 'event.tax_percentage') * 100)],
        );
    }

    public function testProcessWebhookExpirationSubscription(): void
    {
        $user = $this->makeStudent();
        $endDate = Carbon::now()->addDays(3);
        [$appStoreProductId, $playStoreProductId, $product] = $this->makeProductWithStoreIds(true);
        $payload = $this->makeWebhookSubscriptionPayloadWith($user->getKey(), 'INITIAL_PURCHASE', $appStoreProductId, 'NORMAL', 'APP_STORE', $endDate);

        $this->postJson('api/webhooks/revenuecat', $payload)
            ->assertOk();

        $this->assertProcessedWebhook(
            $product,
            $user,
            (int)(Arr::get($payload, 'event.price_in_purchased_currency') * 100),
            ['end_date' => $endDate, 'status' => SubscriptionStatus::ACTIVE],
            ['tax' => (int)(Arr::get($payload, 'event.tax_percentage') * 100)],
            ['currency' => Arr::get($payload, 'event.currency')]
        );

        $payload['event']['type'] = 'EXPIRATION';
        $this->postJson('api/webhooks/revenuecat', $payload)
            ->assertOk();

        $this->assertProcessedWebhook(
            $product,
            $user,
            (int)(Arr::get($payload, 'event.price_in_purchased_currency') * 100),
            ['end_date' => $endDate, 'status' => SubscriptionStatus::EXPIRED],
            ['tax' => (int)(Arr::get($payload, 'event.tax_percentage') * 100)],
            ['currency' => Arr::get($payload, 'event.currency')]
        );
    }

    public function testProcessWebhookUserNotFound(): void
    {
        $user = $this->makeStudent();
        [$appStoreProductId, $playStoreProductId, $product] = $this->makeProductWithStoreIds(true);
        $payload = $this->makeWebhookPayloadWith($user->getKey() * -1, 'INITIAL_PURCHASE', $appStoreProductId, 'NORMAL', 'APP_STORE');

        $this->postJson('api/webhooks/revenuecat', $payload)
            ->assertUnprocessable();

        $this->assertDatabaseMissing('products_users', [
            'product_id' => $product->getKey(),
            'user_id' => $user->getKey(),
        ]);
        $this->assertDatabaseMissing('orders', [
            'user_id' => $user->getKey(),
            'status' => OrderStatus::PAID
        ]);
        $this->assertDatabaseMissing('payments', [
            'user_id' => $user->getKey(),
            'status' => PaymentStatus::PAID,
        ]);
    }

    public function testProcessWebhookProductNotFound(): void
    {
        $user = $this->makeStudent();
        [$appStoreProductId, $playStoreProductId, $product] = $this->makeProductWithStoreIds(true);
        $payload = $this->makeWebhookPayloadWith($user->getKey() * -1, 'INITIAL_PURCHASE', $this->faker->word, 'NORMAL', 'APP_STORE');

        $this->postJson('api/webhooks/revenuecat', $payload)
            ->assertUnprocessable();

        $this->assertDatabaseMissing('products_users', [
            'product_id' => $product->getKey(),
            'user_id' => $user->getKey(),
        ]);
        $this->assertDatabaseMissing('orders', [
            'user_id' => $user->getKey(),
            'status' => OrderStatus::PAID
        ]);
        $this->assertDatabaseMissing('payments', [
            'user_id' => $user->getKey(),
            'status' => PaymentStatus::PAID,
        ]);
    }

    public function testProcessWebhookAuthEnabledForbidden(): void
    {
        Config::set(EscolaLmsRevenueCatIntegrationServiceProvider::CONFIG_KEY . '.webhooks.auth.enabled', true);
        Config::set(EscolaLmsRevenueCatIntegrationServiceProvider::CONFIG_KEY . '.webhooks.auth.key', $this->faker->uuid);

        $this->postJson('api/webhooks/revenuecat', $this->makeWebhookPayload())
            ->assertForbidden();
    }

    public function testProcessWebhookAuthEnabledForbiddenNullKey(): void
    {
        Config::set(EscolaLmsRevenueCatIntegrationServiceProvider::CONFIG_KEY . '.webhooks.auth.enabled', true);

        $this->postJson('api/webhooks/revenuecat', $this->makeWebhookPayload())
            ->assertForbidden();
    }

    private function assertProcessedWebhook(Product $product, User $user, int $price, ?array $productsUsersData = [], ?array $ordersData = [], ?array $paymentsData = []): void
    {
        $this->assertDatabaseHas('products_users', array_merge([
            'product_id' => $product->getKey(),
            'user_id' => $user->getKey(),
        ], $productsUsersData));

        $this->assertDatabaseHas('orders', array_merge([
            'user_id' => $user->getKey(),
            'status' => OrderStatus::PAID,
            'total' => $price,
        ], $ordersData));

        $this->assertDatabaseHas('payments', array_merge([
            'user_id' => $user->getKey(),
            'status' => PaymentStatus::PAID,
            'amount' => $price,
            'driver' => 'revenuecat'
        ], $paymentsData));
    }
}
