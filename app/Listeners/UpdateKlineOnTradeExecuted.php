<?php

namespace App\Listeners;

use App\Events\KlineUpdated;
use App\Events\TradeExecuted;
use App\Models\Kline;
use Illuminate\Support\Facades\Log;

class UpdateKlineOnTradeExecuted
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(TradeExecuted $event): void
    {
        $trade = $event->trade;
        $kline = $this->activeCandle($trade->buyerOrder->symbol);
        if (! $kline) {
            return; // No active candle, skip update
        }

        $tradeTime = $trade->trade_time;
        $quantity = $trade->quantity;
        $quoteAmount = $trade->quote_amount;

        // Only update if trade is within this candle's timeframe
        if ($tradeTime <= $kline->close_time) {
            $kline->update([
                'open' => $this->getOpen($kline->open, $trade->price),
                'high' => $this->getHigh($kline->high, $trade->price),
                'low' => $this->getLow($kline->low, $trade->price),
                'close' => $this->getClose($trade->price),
                'volume' => $this->getVolume($kline->volume, $quantity),
                'quote_volume' => $this->getQuoteVolume($kline->quote_volume, $quoteAmount),
                'trades_count' => $kline->trades_count + 1,
            ]);

            // Broadcast kline update for real-time chart
            event(new KlineUpdated($kline));
        }
    }

    /**
     * Get opening price (first trade sets this, then stays fixed).
     * Note: Uses float for demo simplicity. Production should use bccomp() for perfect precision.
     */
    public function getOpen(?float $currentOpen, float $tradePrice): float
    {
        return $currentOpen ?? $tradePrice;
    }

    /**
     * Get the higher price between current high and trade price.
     */
    public function getHigh(float $currentHigh, float $tradePrice): float
    {
        return max($currentHigh, $tradePrice);
    }

    /**
     * Get the lower price between current low and trade price.
     */
    public function getLow(float $currentLow, float $tradePrice): float
    {
        return min($currentLow, $tradePrice);
    }

    /**
     * Get closing price (always the latest trade price).
     */
    public function getClose(float $tradePrice): float
    {
        return $tradePrice;
    }

    /**
     * Accumulate volume (sum of all trade quantities).
     */
    public function getVolume(float $currentVolume, float $tradeVolume): float
    {
        return $currentVolume + $tradeVolume;
    }

    /**
     * Accumulate quote volume (sum of all trade values in quote currency).
     */
    public function getQuoteVolume(float $currentQuoteVolume, float $tradeQuoteAmount): float
    {
        return $currentQuoteVolume + $tradeQuoteAmount;
    }

    /**
     * Get the active (non-closed) candle for the symbol.
     */
    public function activeCandle(string $symbol): ?Kline
    {
        return Kline::query()
            ->where('symbol', $symbol)
            ->where('is_closed', false)
            ->latest('id')
            ->first();
    }
}
