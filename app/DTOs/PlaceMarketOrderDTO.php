<?php

namespace App\DTOs;

use App\Http\Requests\PlaceOrderRequest;
use App\Models\User;

class PlaceMarketOrderDTO
{
    public function __construct(
        public readonly User $user,
        public readonly string $symbol,
        public readonly string $side,
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
     * Get the base asset symbol (e.g., BTC in BTCUSDT).
     */
    public function getBaseAsset(): string
    {
        [$base, ] = $this->parseSymbol();

        return $base;
    }

    /**
     * Get the quote asset symbol (e.g., USDT in BTCUSDT).
     */
    public function getQuoteAsset(): string
    {
        [, $quote] = $this->parseSymbol();

        return $quote;
    }

    /**
     * Get the asset symbol to lock (for buy: quote, for sell: base).
     */
    public function getAssetToLock(): string
    {
        return $this->isBuyOrder() ? $this->getQuoteAsset() : $this->getBaseAsset();
    }
}
