<?php

namespace App\Services\Trading\Concerns;

trait ParsesSymbol
{
    /**
     * Parse trading symbol into base and quote currencies.
     *
     * Supports both formats:
     * - BTC/USDT (standard format)
     * - BTCUSDT (legacy format)
     *
     * @return array{0: string, 1: string} [$base, $quote]
     */
    private function parseSymbol(string $symbol): array
    {
        if (str_contains($symbol, '/')) {
            return explode('/', $symbol);
        }

        // Legacy format BTCUSDT -> extract BTC and USDT
        // Common quote currencies to check
        $quotePatterns = ['USDT', 'USDC', 'BUSD', 'USD', 'BTC', 'ETH', 'BNB'];

        foreach ($quotePatterns as $quote) {
            if (str_ends_with($symbol, $quote)) {
                $base = substr($symbol, 0, -strlen($quote));

                return [$base, $quote];
            }
        }

        // Fallback: assume last 4 characters are quote
        $quote = substr($symbol, -4);
        $base = substr($symbol, 0, -4);

        return [$base, $quote];
    }
}
