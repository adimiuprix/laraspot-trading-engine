<?php

namespace App\Listeners;

use App\Events\KlineUpdated;
use App\Events\TradeExecuted;
use App\Models\Kline;
use App\Models\Trade;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class UpdateKlineOnTradeExecuted
{
    /**
     * Handle the event.
     */
    public function handle(TradeExecuted $event): void
    {
        $trade = $event->trade;
        $symbol = $trade->buyerOrder->symbol;

        // Get ALL active candles for this symbol (all intervals)
        $klines = $this->getActiveCandles($symbol);

        if ($klines->isEmpty()) {
            Log::warning('No active candles found for symbol', [
                'symbol' => $symbol,
                'trade_id' => $trade->id,
            ]);

            return;
        }

        $tradeTime = $trade->trade_time;

        // Update all active intervals
        foreach ($klines as $kline) {
            // Only update if trade is within this candle's timeframe
            if ($tradeTime <= $kline->close_time) {
                $this->updateKline($kline, $trade);

                // Broadcast kline update for real-time chart
                event(new KlineUpdated($kline));
            }
        }
    }

    /**
     * Update a single kline with trade data.
     */
    private function updateKline(Kline $kline, Trade $trade): void
    {
        $kline->update([
            'open' => $this->getOpen($kline->open, $trade->price),
            'high' => $this->getHigh($kline->high, $trade->price),
            'low' => $this->getLow($kline->low, $trade->price),
            'close' => $trade->price,
            'volume' => $this->getVolume($kline->volume, $trade->quantity),
            'quote_volume' => $this->getQuoteVolume($kline->quote_volume, $trade->quote_amount),
            'trades_count' => $kline->trades_count + 1,
        ]);
    }

    /**
     * Get opening price (first trade sets this, then stays fixed).
     */
    private function getOpen(?string $currentOpen, string $tradePrice): string
    {
        return $currentOpen ?? $tradePrice;
    }

    /**
     * Get the higher price between current high and trade price.
     */
    private function getHigh(string $currentHigh, string $tradePrice): string
    {
        return bccomp($currentHigh, $tradePrice, 8) >= 0
            ? $currentHigh
            : $tradePrice;
    }

    /**
     * Get the lower price between current low and trade price.
     */
    private function getLow(string $currentLow, string $tradePrice): string
    {
        return bccomp($currentLow, $tradePrice, 8) <= 0
            ? $currentLow
            : $tradePrice;
    }

    /**
     * Accumulate volume (sum of all trade quantities in base currency).
     */
    private function getVolume(string $currentVolume, string $tradeVolume): string
    {
        return bcadd($currentVolume, $tradeVolume, 8);
    }

    /**
     * Accumulate quote volume (sum of all trade values in quote currency).
     */
    private function getQuoteVolume(string $currentQuoteVolume, string $tradeQuoteAmount): string
    {
        return bcadd($currentQuoteVolume, $tradeQuoteAmount, 8);
    }

    /**
     * Get all active (non-closed) candles for the symbol.
     *
     * @return Collection<int, Kline>
     */
    private function getActiveCandles(string $symbol): Collection
    {
        return Kline::query()
            ->where('symbol', $symbol)
            ->where('is_closed', false)
            ->get();
    }
}
