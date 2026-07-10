<?php

namespace App\DTOs;

use App\Http\Requests\PlaceOrderRequest;
use App\Models\User;

class PlaceLimitOrderDTO
{
    public function __construct(
        public readonly User $user,
        public readonly string $symbol,
        public readonly string $side,
        public readonly string $price,
        public readonly string $quantity,
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
            quantity: $request->validated('quantity'),
        );
    }

    /**
     * Parse symbol into base and quote currencies.
     *
     * @return array{0: string, 1: string}
     */
    private function parseSymbol(): array
    {
        if (str_contains($this->symbol, '/')) {
            return explode('/', $this->symbol);
        }

        // Legacy format BTCUSDT -> extract BTC and USDT
        $quotePatterns = ['USDT', 'USDC', 'BUSD', 'USD', 'BTC', 'ETH', 'BNB'];

        foreach ($quotePatterns as $quote) {
            if (str_ends_with($this->symbol, $quote)) {
                $base = substr($this->symbol, 0, -strlen($quote));

                return [$base, $quote];
            }
        }

        // Fallback: assume last 4 characters are quote
        $quote = substr($this->symbol, -4);
        $base = substr($this->symbol, 0, -4);

        return [$base, $quote];
    }

    /**
     * Get the total value for buy orders (price * quantity).
     */
    public function getTotalValue(): string
    {
        return bcmul($this->price, $this->quantity, 8);
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
        [$base, $quote] = $this->parseSymbol();

        return $this->isBuyOrder() ? $quote : $base;
    }

    /**
     * Get the quantity to lock.
     */
    public function getquantityToLock(): string
    {
        // Buy: lock (price * quantity) in quote currency
        // Sell: lock quantity in base currency
        return $this->isBuyOrder() ? $this->getTotalValue() : $this->quantity;
    }
}
