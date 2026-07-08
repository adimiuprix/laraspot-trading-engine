<?php

namespace App\Services\Trading;

use App\Events\BalanceUpdated;
use App\Events\OrderStatusChanged;
use App\Events\TradeExecuted as TradeExecutedEvent;
use App\Models\Order;
use App\Models\Trade;
use App\Repositories\TradeRepository;
use App\Services\AssetService;

class TradeExecutionService
{
    public function __construct(
        private TradeRepository $tradeRepository,
        private AssetService $assetService,
        private FeeCalculator $feeCalculator
    ) {}

    /**
     * Execute a trade between buy and sell orders.
     */
    public function executeTrade(Order $buyOrder, Order $sellOrder): Trade
    {
        // Trade price is the maker's price (the order that was placed first)
        $tradePrice = $sellOrder->price;
        $tradeAmount = $buyOrder->amount; // Assuming full fill for MVP

        // Calculate commission
        $commission = $this->feeCalculator->calculate($tradePrice, $tradeAmount);

        // Create trade record
        $trade = $this->tradeRepository->create([
            'buyer_order_id' => $buyOrder->id,
            'seller_order_id' => $sellOrder->id,
            'price' => $tradePrice,
            'amount' => $tradeAmount,
            'commission' => $commission,
        ]);

        // Update balances
        $this->updateBalances($buyOrder, $sellOrder, $tradePrice, $tradeAmount, $commission);

        // Mark orders as filled
        $buyOrder->markAsFilled();
        $sellOrder->markAsFilled();

        // Broadcast order status changes
        event(new OrderStatusChanged($buyOrder));
        event(new OrderStatusChanged($sellOrder));

        // Broadcast trade executed
        event(new TradeExecutedEvent($trade));

        return $trade;
    }

    /**
     * Update balances after trade execution.
     */
    private function updateBalances(Order $buyOrder, Order $sellOrder, string $price, string $amount, string $commission): void
    {
        // Extract base and quote from symbol (e.g., BTC/USDT)
        [$base, $quote] = explode('/', $buyOrder->symbol);

        $total = bcmul($price, $amount, 8);

        // Buyer: Release locked USDT, receive BTC (minus fee)
        $this->assetService->releaseLocked($buyOrder->user, $quote, $total);
        $btcToReceive = bcsub($amount, bcdiv($commission, $price, 8), 8); // Deduct fee in BTC
        $this->assetService->addAmount($buyOrder->user, $base, $btcToReceive);

        // Broadcast buyer balance updates
        $buyerQuoteAsset = $buyOrder->user->getAsset($quote);
        $buyerBaseAsset = $buyOrder->user->getAsset($base);
        if ($buyerQuoteAsset) {
            event(new BalanceUpdated($buyerQuoteAsset));
        }
        if ($buyerBaseAsset) {
            event(new BalanceUpdated($buyerBaseAsset));
        }

        // Seller: Release locked BTC, receive USDT (minus fee)
        $this->assetService->releaseLocked($sellOrder->user, $base, $amount);
        $usdtToReceive = bcsub($total, $commission, 8); // Deduct fee in USDT
        $this->assetService->addAmount($sellOrder->user, $quote, $usdtToReceive);

        // Broadcast seller balance updates
        $sellerBaseAsset = $sellOrder->user->getAsset($base);
        $sellerQuoteAsset = $sellOrder->user->getAsset($quote);
        if ($sellerBaseAsset) {
            event(new BalanceUpdated($sellerBaseAsset));
        }
        if ($sellerQuoteAsset) {
            event(new BalanceUpdated($sellerQuoteAsset));
        }
    }
}
