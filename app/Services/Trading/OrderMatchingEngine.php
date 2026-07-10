<?php

namespace App\Services\Trading;

use App\DTOs\MarketOrderResultDTO;
use App\DTOs\PlaceMarketOrderDTO;
use App\Models\Order;
use App\Repositories\OrderRepository;
use Illuminate\Support\Collection;

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

            // Calculate fill quantity (minimum of both remaining quantitys)
            $fillquantity = $this->calculateFillquantity($order, $matchOrder);

            // Execute partial trade
            if ($order->isBuyOrder()) {
                $this->tradeExecutionService->executeTrade($order, $matchOrder, $fillquantity);
            } else {
                $this->tradeExecutionService->executeTrade($matchOrder, $order, $fillquantity);
            }

            // Refresh order to get latest filled_quantity
            $order->refresh();
        }

        // Order stays open if partially filled, or marked as filled if 100%
    }

    /**
     * Calculate how much can be filled between two orders.
     */
    private function calculateFillquantity(Order $order1, Order $order2): string
    {
        // Take minimum of both remaining quantitys
        $remaining1 = $order1->remaining_quantity;
        $remaining2 = $order2->remaining_quantity;

        return bccomp($remaining1, $remaining2, 8) <= 0
            ? $remaining1
            : $remaining2;
    }

    /**
     * Execute market order against available limit orders.
     */
    public function executeMarketOrder(
        PlaceMarketOrderDTO $marketOrder,
        Collection $availableOrders
    ): MarketOrderResultDTO {
        $remainingquantity = $marketOrder->quantity;
        $totalCost = '0';
        $trades = collect();

        foreach ($availableOrders as $limitOrder) {
            if (bccomp($remainingquantity, '0', 8) <= 0) {
                break; // Market order fully filled
            }

            // Calculate fill quantity for this trade
            $fillquantity = bccomp($remainingquantity, $limitOrder->remaining_quantity, 8) <= 0
                ? $remainingquantity
                : $limitOrder->remaining_quantity;

            $tradePrice = $limitOrder->price;

            // Create trade record
            // For market orders, we use the limit order ID for both sides
            // and track the actual users via buyer_id/seller_id fields
            $trade = app(\App\Repositories\TradeRepository::class)->create([
                'buyer_order_id' => $marketOrder->isBuyOrder() ? $limitOrder->id : $limitOrder->id,
                'seller_order_id' => $marketOrder->isSellOrder() ? $limitOrder->id : $limitOrder->id,
                'buyer_id' => $marketOrder->isBuyOrder() ? $marketOrder->user->id : $limitOrder->user_id,
                'seller_id' => $marketOrder->isSellOrder() ? $marketOrder->user->id : $limitOrder->user_id,
                'price' => $tradePrice,
                'quantity' => $fillquantity,
                'commission' => '0', // Fee calculated separately
            ]);

            // Handle balance transfer
            [$base, $quote] = $this->parseSymbol($marketOrder->symbol);
            $tradeCost = bcmul($tradePrice, $fillquantity, 8);

            $assetService = app(\App\Services\AssetService::class);

            if ($marketOrder->isBuyOrder()) {
                // Market buy: Market user pays USDT, gets BTC
                // Limit seller: releases BTC, gets USDT
                
                // Release locked from limit order seller
                $assetService->releaseLocked($limitOrder->user, $base, $fillquantity);
                $assetService->addquantity($limitOrder->user, $quote, $tradeCost);

                // Broadcast balance updates for limit order user
                event(new \App\Events\BalanceUpdated($assetService->getOrCreateAsset($limitOrder->user, $base)));
                event(new \App\Events\BalanceUpdated($assetService->getOrCreateAsset($limitOrder->user, $quote)));
            } else {
                // Market sell: Market user gives BTC, gets USDT
                // Limit buyer: releases USDT, gets BTC
                
                $assetService->releaseLocked($limitOrder->user, $quote, $tradeCost);
                $assetService->addquantity($limitOrder->user, $base, $fillquantity);

                // Broadcast balance updates for limit order user
                event(new \App\Events\BalanceUpdated($assetService->getOrCreateAsset($limitOrder->user, $quote)));
                event(new \App\Events\BalanceUpdated($assetService->getOrCreateAsset($limitOrder->user, $base)));
            }

            $trades->push($trade);
            $remainingquantity = bcsub($remainingquantity, $fillquantity, 8);
            $totalCost = bcadd($totalCost, $tradeCost, 8);

            // Update limit order
            $limitOrder->addFill($fillquantity);
            $limitOrder->save();

            // Broadcast limit order status change
            event(new \App\Events\OrderStatusChanged($limitOrder));

            // Broadcast trade executed
            event(new \App\Events\TradeExecuted($trade));
        }

        // Calculate weighted average price
        $filledquantity = bcsub($marketOrder->quantity, $remainingquantity, 8);
        $averagePrice = bccomp($filledquantity, '0', 8) > 0
            ? bcdiv($totalCost, $filledquantity, 8)
            : '0';

        return new MarketOrderResultDTO(
            averagePrice: $averagePrice,
            filledquantity: $filledquantity,
            totalCost: $totalCost,
            trades: $trades,
            tradesCount: $trades->count()
        );
    }

    /**
     * Parse symbol into base and quote currencies.
     */
    private function parseSymbol(string $symbol): array
    {
        if (str_contains($symbol, '/')) {
            return explode('/', $symbol);
        }

        $quotePatterns = ['USDT', 'USDC', 'BUSD', 'USD', 'BTC', 'ETH', 'BNB'];

        foreach ($quotePatterns as $quote) {
            if (str_ends_with($symbol, $quote)) {
                $base = substr($symbol, 0, -strlen($quote));
                return [$base, $quote];
            }
        }

        $quote = substr($symbol, -4);
        $base = substr($symbol, 0, -4);

        return [$base, $quote];
    }
}
