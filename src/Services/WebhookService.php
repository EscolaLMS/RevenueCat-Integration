<?php

namespace EscolaLms\RevenueCatIntegration\Services;

use EscolaLms\Cart\Contracts\Productable;
use EscolaLms\Cart\Events\ProductableAttached;
use EscolaLms\Cart\Events\ProductAttached;
use EscolaLms\Cart\Services\Contracts\ProductServiceContract;
use EscolaLms\Payments\Enums\PaymentStatus;
use EscolaLms\Payments\Models\Payment;
use EscolaLms\RevenueCatIntegration\Dtos\ProcessWebhookDto;
use EscolaLms\RevenueCatIntegration\Exceptions\ResourcesNotFound;
use EscolaLms\RevenueCatIntegration\Services\Contracts\WebhookServiceContract;
use EscolaLms\Cart\Enums\OrderStatus;
use EscolaLms\Cart\Enums\SubscriptionStatus;
use EscolaLms\Cart\Events\OrderCreated;
use EscolaLms\Cart\Models\Order;
use EscolaLms\Cart\Models\OrderItem;
use EscolaLms\Cart\Models\Product;
use EscolaLms\Cart\Models\ProductUser;
use EscolaLms\Cart\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class WebhookService implements WebhookServiceContract
{
    private ProductServiceContract $productService;

    public function __construct(ProductServiceContract $productService)
    {
        $this->productService = $productService;
    }

    /**
     * @throws ResourcesNotFound
     */
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
            throw new ResourcesNotFound();
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

    protected function processNonRenewingPurchase(User $user, Product $product, ProcessWebhookDto $dto): void
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

    protected function processSubscriptionPaused(User $user, Product $product, ProcessWebhookDto $dto): void
    {
        [$endDate, $status] = $this->prepareOrderData($dto);

        $this->createProductUser($user, $product, $endDate, $status);
    }

    protected function makeOrder(User $user, Product $product, ProcessWebhookDto $dto): void
    {
        [$endDate, $status] = $this->prepareOrderData($dto);

        $order = $this->createOrder($product, $user->getKey(), $dto, $dto->isTrial());
        $payment = $this->createPayment($order, $dto);

        $this->createProductUser($user, $product, $endDate, $status);
    }

    private function prepareOrderData(ProcessWebhookDto $dto): array
    {
        $endDate = null;
        $status = null;

        if ($dto->isSubscription()) {
            $endDate = $dto->expirationAt();
            $status = $endDate->isFuture() ? SubscriptionStatus::ACTIVE : SubscriptionStatus::EXPIRED;
        }

        return [
            $endDate,
            $status,
        ];
    }

    private function createProductUser(User $user, Product $product, ?Carbon $endDate = null, ?string $status = null): void
    {
        $quantity = 1;
        ProductUser::query()
            ->updateOrCreate(
                ['user_id' => $user->getKey(), 'product_id' => $product->getKey()],
                ['quantity' => $quantity, 'end_date' => $endDate, 'status' => $status]
            );

        foreach ($product->productables as $productProductable) {
            Log::debug(__('Checking if productable can be processed'));
            if ($this->productService->isProductableClassRegistered($productProductable->productable_type)) {
                $productable = $this->productService->findProductable($productProductable->productable_type, $productProductable->productable_id);
                if (is_null($productable)) {
                    Log::debug([
                        'product' => [
                            'id' => $product->getKey(),
                            'name' => $product->name,
                            'extended_model' => $productProductable->productable_type,
                            'extended_model_id' => $productProductable->productable_id,
                        ],
                        'message' => __('Attached product is not exists')
                    ]);
                    throw new Exception(__('Attached product is not exists'));
                }
                $this->attachProductableToUser($productable, $user, $productProductable->quantity * $quantity, $product);
            }
        }
        event(new ProductAttached($product, $user, $quantity));
    }

    public function attachProductableToUser(Productable $productable, \EscolaLms\Core\Models\User $user, int $quantity = 1, ?Product $product = null): void
    {
        Log::debug(__('Attaching productable to user'), [
            'productable' => [
                'id' => $productable->getKey(),
                'name' => $productable->getName(),
            ],
            'user' => [
                'id' => $user->getKey(),
                'email' => $user->email,
            ],
            'quantity' => $quantity,
        ]);

        assert($productable instanceof Model);
        try {
            $productable->attachToUser($user, $quantity, $product);
            Log::debug(
                'Productable (should be) attached to user.',
                [
                    'productable_owned' => $productable->getOwnedByUserAttribute($user),
                    'productable_owned_through_product' => $this->productService->productableIsOwnedByUserThroughProduct($productable, $user),
                ]
            );
        } catch (Exception $ex) {
            Log::error(__('Failed to attach productable to user'), [
                'exception' => $ex->getMessage(),
            ]);
        }
        event(new ProductableAttached($productable, $user, $quantity));
    }

    private function createOrder(Product $product, int $userId, ProcessWebhookDto $dto, ?bool $isTrial = false): Order
    {
        /** @var User $user */
        $user = User::find($userId);

        $user->orders()->where('status', OrderStatus::PROCESSING)->update(['status' => OrderStatus::CANCELLED]);
        $user->orders()->where('status', OrderStatus::TRIAL_PROCESSING)->update(['status' => OrderStatus::TRIAL_CANCELLED]);

        $order = new Order();
        $order->user_id = $user->getKey();
        $order->total = $dto->totalPrice() ?? 0;
        $order->subtotal = $dto->subtotalPrice() ?? 0;
        $order->tax = $dto->taxRate() ?? 0;
        $order->status = $isTrial ? OrderStatus::TRIAL_PAID : OrderStatus::PAID;
        $order->save();

        $this->createOrderItems($order, $product, $dto);

        event(new OrderCreated($order));

        return $order;
    }

    private function createOrderItems(Order $order, Product $product, ProcessWebhookDto $dto): OrderItem
    {
        return OrderItem::create([
            'buyable_type' => Product::class,
            'buyable_id' => $product->getKey(),
            'name' => $product->name ?? null,
            'price' => $dto->totalPrice() ?? 0,
            'quantity' => 1,
            'tax_rate' => $dto->taxRate() ?? 0,
            'extra_fees' => 0,
            'order_id' => $order->getKey(),
        ]);
    }

    private function createPayment(Order $order, ProcessWebhookDto $dto): Payment
    {
        $payment = Payment::create([
            'amount' => $order->getPaymentAmount(),
            'currency' => $dto->currency(),
            'description' => $order->getPaymentDescription(),
            'order_id' => $order->getPaymentOrderId(),
            'status' => PaymentStatus::PAID,
            'driver' => 'revenuecat',
            'gateway_order_id' => $dto->getTransactionId()
        ]);

        if ($order->getUser()) {
            $payment->user()->associate($order->getUser());
        }

        $payment->payable()->associate($order);
        $payment->save();

        return $payment;
    }
}
