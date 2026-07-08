<?php

namespace App\Repositories;

use App\Models\Trade;
use Illuminate\Database\Eloquent\Collection;

class TradeRepository
{
    /**
     * Create a new trade.
     */
    public function create(array $data): Trade
    {
        return Trade::create($data);
    }

    /**
     * Get recent trades for a symbol.
     */
    public function getRecentTrades(string $symbol, int $limit = 50): Collection
    {
        return Trade::whereHas('buyerOrder', fn ($q) => $q->where('symbol', $symbol))
            ->with(['buyerOrder', 'sellerOrder'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get trades for a specific user (as buyer or seller).
     */
    public function getUserTrades(int $userId): Collection
    {
        return Trade::where(function ($q) use ($userId) {
            $q->whereHas('buyerOrder', fn ($subQ) => $subQ->where('user_id', $userId))
                ->orWhereHas('sellerOrder', fn ($subQ) => $subQ->where('user_id', $userId));
        })
            ->with(['buyerOrder', 'sellerOrder'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get the last trade price for a symbol.
     */
    public function getLastPrice(string $symbol): ?string
    {
        $trade = Trade::whereHas('buyerOrder', fn ($q) => $q->where('symbol', $symbol))
            ->orderBy('created_at', 'desc')
            ->first();

        return $trade?->price;
    }
}
