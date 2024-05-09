<?php

namespace EscolaLms\RevenueCatIntegration\Dtos;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use EscolaLms\Payments\Enums\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;


class ProcessWebhookDto implements DtoContract, InstantiateFromRequest
{

    private string $userId;

    private string $eventType;

    private ?string $periodType;

    private ?string $productId;

    private ?string $store;

    private ?float $price;

    private ?string $currency;

    private ?float $priceInPurchaseCurrency;

    private ?float $taxPercentage;

    private ?string $transactionId;

    private ?int $expirationAtMs;

    public function __construct(string $userId, string $eventType, ?string $periodType, ?string $productId, ?string $store, ?float $price, ?string $currency, ?float $priceInPurchaseCurrency, ?float $taxPercentage, ?string $transactionId, ?int $expirationAtMs)
    {
        $this->userId = $userId;
        $this->eventType = $eventType;
        $this->periodType = $periodType;
        $this->productId = $productId;
        $this->store = $store;
        $this->price = $price;
        $this->currency = $currency;
        $this->priceInPurchaseCurrency = $priceInPurchaseCurrency;
        $this->taxPercentage = $taxPercentage;
        $this->transactionId = $transactionId;
        $this->expirationAtMs = $expirationAtMs;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getPeriodType(): ?string
    {
        return $this->periodType;
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function getStore(): ?string
    {
        return $this->store;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getPriceInPurchaseCurrency(): ?float
    {
        return $this->priceInPurchaseCurrency;
    }

    public function getTaxPercentage(): ?float
    {
        return $this->taxPercentage;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function getExpirationAtMs(): ?int
    {
        return $this->expirationAtMs;
    }

    public function eventTypeName(): string
    {
        return Str::ucfirst(Str::camel(Str::lower($this->getEventType())));
    }

    public function storeName(): string
    {
        return Str::lower($this->getStore());
    }

    public function currency(): string
    {
        return Currency::hasValue($this->getCurrency())
            ? Currency::fromValue($this->getCurrency())->value
            : Currency::USD;
    }

    public function totalPrice(): int
    {
        $totalPrice = Currency::hasValue($this->getCurrency()) ? $this->getPriceInPurchaseCurrency() : $this->getPrice();

        return (int)($totalPrice * 100);
    }

    public function subtotalPrice(): int
    {
        if (!Currency::hasValue($this->getCurrency())) {
            return $this->getPrice();
        }

        return (int)(($this->totalPrice() / ($this->taxRate() + 100)) * 100);
    }

    public function taxRate(): int
    {
        if (!Currency::hasValue($this->getCurrency())) {
            return 0;
        }

        return (int)($this->getTaxPercentage() * 100);
    }

    public function isTrial(): bool
    {
        return Str::lower($this->getPeriodType()) === 'trial';
    }

    public function expirationAt(): Carbon
    {
        return Carbon::createFromTimestampMs($this->getExpirationAtMs());
    }

    public function isSubscription(): bool
    {
        return $this->getExpirationAtMs() != null;
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->getUserId(),
            'event_type' => $this->getEventType(),
            'period_type' => $this->getPeriodType(),
            'product_id' => $this->getProductId(),
            'store' => $this->getStore(),
            'price' => $this->getPrice(),
            'currency' => $this->getCurrency(),
            'price_in_purchased_currency' => $this->getPriceInPurchaseCurrency(),
            'tax_percentage' => $this->getTaxPercentage(),
            'transaction_id' => $this->getTransactionId(),
            'expiration_at_ms' => $this->getExpirationAtMs(),
        ];
    }

    public static function instantiateFromRequest(Request $request): self
    {
        return new static(
            $request->input('event.app_user_id'),
            $request->input('event.type'),
            $request->input('event.period_type'),
            $request->input('event.product_id'),
            $request->input('event.store'),
            $request->input('event.price'),
            $request->input('event.currency'),
            $request->input('event.price_in_purchased_currency'),
            $request->input('event.tax_percentage'),
            $request->input('event.transaction_id'),
            $request->input('event.expiration_at_ms'),
        );
    }
}
