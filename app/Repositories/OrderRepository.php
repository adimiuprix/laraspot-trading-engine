<?php

namespace App\Repositories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class OrderRepository
{
    /**
     * Create a new order.
     */
    public function create(array $data): Order
    {
        return Order::create($data);
    }

    /**
     * Find order by ID.
     */
    public function findById(int $id): ?Order
    {
        return Order::find($id);
    }

    /**
     * Get user's orders with optional status filter.
     */
    public function getUserOrders(int $userId, ?string $status = null): Collection
    {
        return Order::where('user_id', $userId)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get open orders for a specific symbol and side.
     * This includes both 'open' and 'partially_filled' orders.
     */
    public function getOpenOrders(string $symbol, string $side): Collection
    {
        return Order::where('symbol', $symbol)
            ->where('side', $side)
            ->open()
            ->orderBy('price', $side === 'buy' ? 'desc' : 'asc')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get orders that can be matched for a given order.
     * For buy orders: get sell orders at or below the price.
     * For sell orders: get buy orders at or above the price.
     */
    public function getMatchableOrders(string $symbol, string $side, string $price): Collection
    {
        $oppositeSide = $side === 'buy' ? 'sell' : 'buy';

        $query = Order::where('symbol', $symbol)
            ->where('side', $oppositeSide)
            ->whereIn('status', ['open', 'partially_filled'])
            ->where('remaining_amount', '>', 0);

        if ($side === 'buy') {
            // Buy order can match sell orders at or below the buy price
            $query->where('price', '<=', $price)
                ->orderBy('price', 'asc'); // Best price first (lowest)
        } else {
            // Sell order can match buy orders at or above the sell price
            $query->where('price', '>=', $price)
                ->orderBy('price', 'desc'); // Best price first (highest)
        }

        return $query->orderBy('created_at', 'asc') // Time priority
            ->get();
    }

    /**
     * Get the best matchable order (single).
     */
    public function getBestMatchableOrder(string $symbol, string $side, string $price): ?Order
    {
        $oppositeSide = $side === 'buy' ? 'sell' : 'buy';

        $query = Order::where('symbol', $symbol)
            ->where('side', $oppositeSide)
            ->whereIn('status', ['open', 'partially_filled'])
            ->where('remaining_amount', '>', 0);

        if ($side === 'buy') {
            $query->where('price', '<=', $price)
                ->orderBy('price', 'asc');
        } else {
            $query->where('price', '>=', $price)
                ->orderBy('price', 'desc');
        }

        return $query->orderBy('created_at', 'asc')
            ->first();
    }

    /**
     * Update order status.
     */
    public function updateStatus(Order $order, string $status): bool
    {
        return $order->update(['status' => $status]);
    }

    /**
     * Get orderbook bids (buy orders) for a symbol.
     */
    public function getBids(string $symbol, int $depth): Collection
    {
        return Order::query()
            ->selectRaw('price, SUM(remaining_amount) as amount')
            ->where('symbol', $symbol)
            ->where('side', 'buy')
            ->open()
            ->groupBy('price')
            ->orderByDesc('price')
            ->limit($depth)
            ->get();
    }

    /**
     * Get orderbook asks (sell orders) for a symbol.
     */
    public function getAsks(string $symbol, int $depth): Collection
    {
        return Order::query()
            ->selectRaw('price, SUM(remaining_amount) as amount')
            ->where('symbol', $symbol)
            ->where('side', 'sell')
            ->open()
            ->groupBy('price')
            ->orderBy('price')
            ->limit($depth)
            ->get();
    }

    /**
     * Get best available orders for market order execution.
     * Returns orders sorted by best price with time priority.
     */
    public function getBestOrdersForMarketExecution(
        string $symbol,
        string $side,
        int $limit = 50
    ): Collection {
        $oppositeSide = $side === 'buy' ? 'sell' : 'buy';

        $query = Order::where('symbol', $symbol)
            ->where('side', $oppositeSide)
            ->whereIn('status', ['open', 'partially_filled'])
            ->where('remaining_amount', '>', 0);

        if ($side === 'buy') {
            // Buy market order: get cheapest sells first
            $query->orderBy('price', 'asc');
        } else {
            // Sell market order: get highest buys first
            $query->orderBy('price', 'desc');
        }

        return $query->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }
}
