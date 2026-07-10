<?php

namespace App\Services\Trading;

use App\DTOs\OrderBookQueryDTO;
use App\Repositories\OrderRepository;
use App\Repositories\TradeRepository;
use Illuminate\Support\Facades\Log;

class OrderBookService
{
    public function __construct(
        private OrderRepository $orderRepository,
        private TradeRepository $tradeRepository
    ) {}

    /**
     * Get orderbook data (bids and asks).
     */
    public function getOrderBook(OrderBookQueryDTO $dto): array
    {
        $bids = $this->orderRepository->getBids($dto->symbol, $dto->depth);
        $asks = $this->orderRepository->getAsks($dto->symbol, $dto->depth);

        // Format bids
        $formattedBids = $bids->map(function ($order) {
            return [
                'price' => $order->price,
                'quantity' => $order->quantity,
                'total' => bcmul($order->price, $order->quantity, 8),
            ];
        })->toArray();

        // Format asks
        $formattedAsks = $asks->map(function ($order) {
            return [
                'price' => $order->price,
                'quantity' => $order->quantity,
                'total' => bcmul($order->price, $order->quantity, 8),
            ];
        })->toArray();

        // Get last price and calculate spread
        $lastPrice = $this->tradeRepository->getLastPrice($dto->symbol) ?? '0';
        $spread = $this->calculateSpread($bids->first(), $asks->first());

        return [
            'bids' => $formattedBids,
            'asks' => $formattedAsks,
            'last_price' => $lastPrice,
            'spread' => $spread,
        ];
    }

    /**
     * Calculate spread between best bid and best ask.
     */
    private function calculateSpread($bestBid, $bestAsk): string
    {
        if (! $bestBid || ! $bestAsk) {
            return '0';
        }

        return bcsub($bestAsk->price, $bestBid->price, 8);
    }
}
