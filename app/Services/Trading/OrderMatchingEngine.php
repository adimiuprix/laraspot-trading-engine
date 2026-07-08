<?php

namespace App\Services\Trading;

use App\Models\Order;
use App\Repositories\OrderRepository;

class OrderMatchingEngine
{
    public function __construct(
        private OrderRepository $orderRepository,
        private TradeExecutionService $tradeExecutionService
    ) {}

    /**
     * Try to match an order with existing opposite orders.
     */
    public function matchOrder(Order $order): void
    {
        // Get matchable orders (opposite side)
        $matchableOrders = $this->orderRepository->getMatchableOrders(
            $order->symbol,
            $order->side,
            $order->price
        );

        // Try to find a full match
        foreach ($matchableOrders as $matchOrder) {
            // Check if amounts match exactly (full fill)
            if (bccomp($order->amount, $matchOrder->amount, 8) === 0) {
                // Perfect match! Execute trade
                if ($order->isBuyOrder()) {
                    $this->tradeExecutionService->executeTrade($order, $matchOrder);
                } else {
                    $this->tradeExecutionService->executeTrade($matchOrder, $order);
                }

                break; // Order filled, stop matching
            }
        }

        // If no perfect match found, order stays open in orderbook
    }
}
