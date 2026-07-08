<?php

namespace App\DTOs;

use App\Http\Requests\PlaceOrderRequest;
use App\Models\User;

class PlaceOrderDTO
{
    public function __construct(
        public readonly User $user,
        public readonly string $symbol,
        public readonly string $side,
        public readonly string $price,
        public readonly string $amount,
    ) {}

    /**
     * Create DTO from validated request.
     */
    public static function fromRequest(PlaceOrderRequest $request): self
    {
        return new self(
            user: $request->user(),
            symbol: $request->validated('symbol'),
            side: $request->validated('side'),
            price: $request->validated('price'),
            amount: $request->validated('amount'),
        );
    }

    /**
     * Get the total value for buy orders (price * amount).
     */
    public function getTotalValue(): string
    {
        return bcmul($this->price, $this->amount, 8);
    }

    /**
     * Check if this is a buy order.
     */
    public function isBuyOrder(): bool
    {
        return $this->side === 'buy';
    }

    /**
     * Check if this is a sell order.
     */
    public function isSellOrder(): bool
    {
        return $this->side === 'sell';
    }

    /**
     * Get the asset symbol to lock (for buy: quote, for sell: base).
     */
    public function getAssetToLock(): string
    {
        // For BTC/USDT:
        // - Buy: lock USDT (quote currency)
        // - Sell: lock BTC (base currency)
        [$base, $quote] = explode('/', $this->symbol);

        return $this->isBuyOrder() ? $quote : $base;
    }

    /**
     * Get the amount to lock.
     */
    public function getAmountToLock(): string
    {
        // Buy: lock (price * amount) in quote currency
        // Sell: lock amount in base currency
        return $this->isBuyOrder() ? $this->getTotalValue() : $this->amount;
    }
}
