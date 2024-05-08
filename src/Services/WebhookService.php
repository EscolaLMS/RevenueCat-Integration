<?php

namespace EscolaLms\RevenueCatIntegration\Services;

use EscolaLms\RevenueCatIntegration\Dtos\ProcessWebhookDto;
use EscolaLms\RevenueCatIntegration\Services\Contracts\WebhookServiceContract;
use EscolaLms\Cart\Enums\OrderStatus;
use EscolaLms\Cart\Enums\ProductType;
use EscolaLms\Cart\Enums\SubscriptionStatus;
use EscolaLms\Cart\Events\OrderCreated;
use EscolaLms\Cart\Models\Order;
use EscolaLms\Cart\Models\OrderItem;
use EscolaLms\Cart\Models\Product;
use EscolaLms\Cart\Models\ProductUser;
use EscolaLms\Cart\Models\User;
use EscolaLms\Cart\Services\Contracts\OrderServiceContract;
use EscolaLms\Payments\Enums\PaymentStatus;
use Illuminate\Support\Facades\Log;

class WebhookService implements WebhookServiceContract
{
    private OrderServiceContract $orderService;

    public function __construct(OrderServiceContract $orderService)
    {
        $this->orderService = $orderService;
    }

    public function process(ProcessWebhookDto $dto): void
    {
        $processorName = 'process' . $dto->eventTypeName();

        if (!$dto->getEventType() || !$dto->getProductId() || !$dto->getStore()) {
            return;
        }

        $user = User::find($dto->getUserId());
        $product = Product::query()->where('fields->in_app_purchase_ids->revenuecat->' . $dto->storeName(), $dto->getProductId())->first();

        if (!$user || !$product) {
            Log::info(get_class($this) . ' User or product not fount', $dto->toArray());
            return;
        }

        if (!method_exists($this, $processorName)) {
            Log::info(get_class($this) . ' Event not supported', $dto->toArray());
            return;
        }

        $this->{$processorName}($user, $product, $dto);
    }

    protected function processInitialPurchase(User $user, Product $product, ProcessWebhookDto $dto): void
    {
        $this->makeOrder($user, $product, $dto);
    }

    protected function processRenewal(User $user, Product $product, ProcessWebhookDto $dto): void
    {
        $this->makeOrder($user, $product, $dto);
    }

    protected function processCancellation(User $user, Product $product): void
    {
        ProductUser::query()
            ->where('user_id', $user->getKey())
            ->where('product_id', $product->getKey())
            ->where('status', SubscriptionStatus::ACTIVE)
            ->update(['status' => SubscriptionStatus::CANCELLED]);
    }

    protected function processExpiration(User $user, Product $product): void
    {
        ProductUser::query()
            ->where('user_id', $user->getKey())
            ->where('product_id', $product->getKey())
            ->where('status', SubscriptionStatus::ACTIVE)
            ->update(['status' => SubscriptionStatus::EXPIRED]);
    }

    private function makeOrder(User $user, Product $product, ProcessWebhookDto $dto): void
    {
        $order = $this->createOrder($product, $user->getKey(), $dto);
        $paymentProcessor = $order->process();

        $parameters = [
            'return_url' => url('/'),
            'email' => $user->email,
            'type' => $product->type,
            'gateway' => 'revenuecat',
            'currency' => $dto->currency(),
        ];

        if (ProductType::isSubscriptionType($product->type)) {
            $parameters += $product->getSubscriptionParameters();
        }

        $paymentProcessor->purchase($parameters);
        $payment = $paymentProcessor->getPayment();

        $payment->gateway_order_id = $dto->getTransactionId();
        $payment->save();

        if ($payment->status->is(PaymentStatus::CANCELLED)) {
            $this->orderService->setCancelled($order);
        }
    }

    public function createOrder(Product $product, int $userId, ProcessWebhookDto $dto): Order
    {
        /** @var User $user */
        $user = User::find($userId);

        $user->orders()->where('status', OrderStatus::PROCESSING)->update(['status' => OrderStatus::CANCELLED]);
        $user->orders()->where('status', OrderStatus::TRIAL_PROCESSING)->update(['status' => OrderStatus::TRIAL_CANCELLED]);

        $order = new Order();
        $order->user_id = $user->getKey();
        $order->total = $dto->isTrial() ? 0 : $dto->totalPrice();
        $order->subtotal = $dto->isTrial() ? 0 : $dto->subtotalPrice();
        $order->tax = $dto->isTrial() ? 0 : $dto->taxRate();
        $order->status = $dto->isTrial() ? OrderStatus::TRIAL_PROCESSING : OrderStatus::PROCESSING;
        $order->save();
        // todo invoice data

        $this->createOrderItems($order, $product, $dto);

        event(new OrderCreated($order));

        return $order;
    }

    public function createOrderItems(Order $order, Product $product, ProcessWebhookDto $dto): OrderItem
    {
        return OrderItem::create([
            'buyable_type' => Product::class,
            'buyable_id' => $product->getKey(),
            'name' => $product->name ?? null,
            'price' => $dto->isTrial() ? 0 : $dto->totalPrice(),
            'quantity' => 1,
            'tax_rate' => $dto->isTrial() ? 0 : $dto->taxRate(),
            'extra_fees' => 0,
            'order_id' => $order->getKey(),
        ]);
    }
}
