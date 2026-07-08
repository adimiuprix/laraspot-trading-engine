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
     * Try to match an order with existing opposite orders (with partial fill support).
     */
    public function matchOrder(Order $order): void
    {
        // Loop until order is fully filled or no more matchable orders
        while (!$order->isFullyFilled()) {
            // Get the best matchable order (best price, earliest time)
            $matchOrder = $this->orderRepository->getBestMatchableOrder(
                $order->symbol,
                $order->side,
                $order->price
            );

            // No more matchable orders, stop matching
            if (! $matchOrder) {
                break;
            }

            // Calculate fill amount (minimum of both remaining amounts)
            $fillAmount = $this->calculateFillAmount($order, $matchOrder);

            // Execute partial trade
            if ($order->isBuyOrder()) {
                $this->tradeExecutionService->executeTrade($order, $matchOrder, $fillAmount);
            } else {
                $this->tradeExecutionService->executeTrade($matchOrder, $order, $fillAmount);
            }

            // Refresh order to get latest filled_amount
            $order->refresh();
        }

        // Order stays open if partially filled, or marked as filled if 100%
    }

    /**
     * Calculate how much can be filled between two orders.
     */
    private function calculateFillAmount(Order $order1, Order $order2): string
    {
        // Take minimum of both remaining amounts
        $remaining1 = $order1->remaining_amount;
        $remaining2 = $order2->remaining_amount;

        return bccomp($remaining1, $remaining2, 8) <= 0
            ? $remaining1
            : $remaining2;
    }
}
