<?php

namespace App\Services\Trading;

use App\Events\BalanceUpdated;
use App\Events\OrderStatusChanged;
use App\Events\TradeExecuted as TradeExecutedEvent;
use App\Models\Order;
use App\Models\Trade;
use App\Repositories\TradeRepository;
use App\Services\AssetService;
use App\Services\Trading\Concerns\ParsesSymbol;
use Carbon\Carbon;

class TradeExecutionService
{
    use ParsesSymbol;

    public function __construct(
        private TradeRepository $tradeRepository,
        private AssetService $assetService,
        private FeeCalculator $feeCalculator
    ) {}

    /**
     * Execute a trade between buy and sell orders (with partial fill support).
     */
    public function executeTrade(Order $buyOrder, Order $sellOrder, string $fillAmount): Trade
    {
        // Trade price is the maker's price (the order that was placed first)
        $tradePrice = $buyOrder->created_at < $sellOrder->created_at
            ? $buyOrder->price
            : $sellOrder->price;

        // Calculate commission for this partial fill
        $commission = $this->feeCalculator->calculate($tradePrice, $fillAmount);

        // Create trade record
        $trade = $this->tradeRepository->create([
            'buyer_order_id' => $buyOrder->id,
            'seller_order_id' => $sellOrder->id,
            'buyer_id' => $buyOrder->user_id,
            'seller_id' => $sellOrder->user_id,
            'trade_time' => Carbon::now()->timestamp,
            'price' => $tradePrice,
            'amount' => $fillAmount, // Partial amount
            'quote_amount' => bcmul($tradePrice, $fillAmount, 8), // Use bcmul for precision
            'commission' => $commission,
            'maker_side' => $buyOrder->created_at < $sellOrder->created_at ? 'buy' : 'sell', // Determine who market maker
        ]);

        // Update balances for this partial fill
        $this->updateBalances($buyOrder, $sellOrder, $tradePrice, $fillAmount, $commission);

        // Update orders' filled_amount and status
        $buyOrder->addFill($fillAmount);
        $sellOrder->addFill($fillAmount);

        // Broadcast order status changes
        event(new OrderStatusChanged($buyOrder));
        event(new OrderStatusChanged($sellOrder));

        // Broadcast trade executed
        event(new TradeExecutedEvent($trade));

        return $trade;
    }

    /**
     * Update balances after trade execution (for partial fill).
     */
    private function updateBalances(Order $buyOrder, Order $sellOrder, string $price, string $fillAmount, string $commission): void
    {
        // Extract base and quote from symbol
        [$base, $quote] = $this->parseSymbol($buyOrder->symbol);

        $total = bcmul($price, $fillAmount, 8);

        // Buyer: Release locked USDT for this fill, receive BTC (minus fee)
        $this->assetService->releaseLocked($buyOrder->user, $quote, $total);
        $btcToReceive = bcsub($fillAmount, bcdiv($commission, $price, 8), 8); // Deduct fee in BTC
        $this->assetService->addAmount($buyOrder->user, $base, $btcToReceive);

        // Broadcast buyer balance updates
        $buyerQuoteAsset = $this->assetService->getOrCreateAsset($buyOrder->user, $quote);
        $buyerBaseAsset = $this->assetService->getOrCreateAsset($buyOrder->user, $base);
        event(new BalanceUpdated($buyerQuoteAsset));
        event(new BalanceUpdated($buyerBaseAsset));

        // Seller: Release locked BTC for this fill, receive USDT (minus fee)
        $this->assetService->releaseLocked($sellOrder->user, $base, $fillAmount);
        $usdtToReceive = bcsub($total, $commission, 8); // Deduct fee in USDT
        $this->assetService->addAmount($sellOrder->user, $quote, $usdtToReceive);

        // Broadcast seller balance updates
        $sellerBaseAsset = $this->assetService->getOrCreateAsset($sellOrder->user, $base);
        $sellerQuoteAsset = $this->assetService->getOrCreateAsset($sellOrder->user, $quote);
        event(new BalanceUpdated($sellerBaseAsset));
        event(new BalanceUpdated($sellerQuoteAsset));
    }
}
