<?php

namespace App\Repositories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

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
     */
    public function getOpenOrders(string $symbol, string $side): Collection
    {
        return Order::where('symbol', $symbol)
            ->where('side', $side)
            ->where('status', 'open')
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
            ->where('status', 'open');

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
        return Order::where('symbol', $symbol)
            ->where('side', 'buy')
            ->where('status', 'open')
            ->orderBy('price', 'desc')
            ->orderBy('created_at', 'asc')
            ->limit($depth)
            ->get();
    }

    /**
     * Get orderbook asks (sell orders) for a symbol.
     */
    public function getAsks(string $symbol, int $depth): Collection
    {
        return Order::where('symbol', $symbol)
            ->where('side', 'sell')
            ->where('status', 'open')
            ->orderBy('price', 'asc')
            ->orderBy('created_at', 'asc')
            ->limit($depth)
            ->get();
    }
}
