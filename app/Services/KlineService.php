<?php

namespace App\Services;

use App\Events\KlineUpdated;
use App\Models\Kline;
use App\Models\Trade;
use App\Repositories\KlineRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KlineService
{
    public function __construct(
        private KlineRepository $klineRepository
    ) {}

    /**
     * Update or create kline from trade data.
     * This is called for EVERY executed trade.
     *
     * Algorithm from OHLCV_Real_Time_Guide.md:
     * 1. Calculate bucket time: floor(timestamp / interval) * interval
     * 2. Find existing kline by bucket
     * 3. If not exists: Create new kline (open=high=low=close=price, volume=quantity)
     * 4. If exists: Update high/low/close/volume
     * 5. Check if kline should be closed (current time >= close_time)
     */
    public function updateOrCreateFromTrade(
        string $symbol,
        string $interval,
        int $timestamp,
        string $price,
        string $quantity
    ): Kline {
        // Get interval in seconds
        $intervalSeconds = $this->getIntervalSeconds($interval);

        // Calculate bucket time: floor(timestamp / interval) * interval
        $openTime = $this->calculateBucketTime($timestamp, $intervalSeconds);
        $closeTime = $openTime + $intervalSeconds - 1;

        // Calculate quote volume (price × quantity)
        $quotequantity = bcmul($price, $quantity, 8);

        // Try to find existing kline
        $kline = $this->klineRepository->findByBucket($symbol, $interval, $openTime);

        $isNewKline = false;

        if (! $kline) {
            // Create new kline (first trade in this interval)
            $kline = $this->klineRepository->create([
                'symbol' => $symbol,
                'interval' => $interval,
                'open_time' => $openTime,
                'close_time' => $closeTime,
                'open' => $price,        // First trade price
                'high' => $price,        // Initialize with first price
                'low' => $price,         // Initialize with first price
                'close' => $price,       // Latest trade price
                'volume' => $quantity,     // Base asset volume
                'quote_volume' => $quotequantity, // Quote asset volume
                'trades_count' => 1,
                'is_closed' => false,    // New kline is always open
            ]);

            $isNewKline = true;

            Log::info("✨ Created new kline", [
                'symbol' => $symbol,
                'interval' => $interval,
                'open_time' => $openTime,
                'price' => $price,
            ]);
        } else {
            // Check if kline should be auto-closed (trade arrived after close_time)
            // This happens when no trades occurred at the end of interval
            if (! $kline->is_closed && $timestamp > $kline->close_time) {
                $kline->markAsClosed();
                
                Log::info("🔒 Auto-closed previous kline (trade arrived late)", [
                    'symbol' => $symbol,
                    'interval' => $interval,
                    'open_time' => $kline->open_time,
                ]);

                // Broadcast the closed kline before creating new one
                event(new KlineUpdated($kline));

                // Now create a new kline for this trade
                return $this->updateOrCreateFromTrade($symbol, $interval, $timestamp, $price, $quantity);
            }

            // Update existing kline (live candle update)
            $kline->updateWithTrade($price, $quantity, $quotequantity);

            Log::debug("📊 Updated live kline", [
                'symbol' => $symbol,
                'interval' => $interval,
                'open_time' => $openTime,
                'price' => $price,
                'trades_count' => $kline->trades_count,
            ]);
        }

        // Broadcast kline update via WebSocket
        // Frontend will detect if this is live update or new candle based on is_closed flag
        event(new KlineUpdated($kline));

        return $kline;
    }

    /**
     * Update klines for all configured intervals from a single trade.
     */
    public function updateAllIntervalsFromTrade(Trade $trade): void
    {
        $symbol = $trade->buyerOrder->symbol;
        $price = $trade->price;
        $quantity = $trade->quantity;
        $timestamp = $trade->created_at->timestamp;

        $intervals = config('trading.kline_intervals', []);

        foreach ($intervals as $intervalName => $intervalSeconds) {
            try {
                $this->updateOrCreateFromTrade(
                    symbol: $symbol,
                    interval: $intervalName,
                    timestamp: $timestamp,
                    price: $price,
                    quantity: $quantity
                );
            } catch (\Exception $e) {
                Log::error("Failed to update kline for interval {$intervalName}", [
                    'symbol' => $symbol,
                    'error' => $e->getMessage(),
                    'trade_id' => $trade->id,
                ]);
            }
        }
    }

    /**
     * Calculate bucket time using formula: floor(timestamp / interval) * interval.
     *
     * Example:
     *   timestamp = 1752043517
     *   interval = 60
     *   bucket = floor(1752043517 / 60) * 60 = 1752043500
     */
    private function calculateBucketTime(int $timestamp, int $intervalSeconds): int
    {
        return (int) floor($timestamp / $intervalSeconds) * $intervalSeconds;
    }

    /**
     * Get interval in seconds from interval name.
     */
    private function getIntervalSeconds(string $interval): int
    {
        $intervals = config('trading.kline_intervals', []);

        if (! isset($intervals[$interval])) {
            throw new \InvalidArgumentException("Invalid interval: {$interval}");
        }

        return $intervals[$interval];
    }

    /**
     * Close klines that have passed their close_time and broadcast updates.
     * This ensures clients get notified when candles are finalized.
     * 
     * This should be run periodically (e.g., via scheduled command every minute).
     */
    public function closeExpiredKlines(): int
    {
        $now = now()->timestamp;
        $unclosedKlines = $this->klineRepository->getUnclosedKlinesBefore($now);

        $closedCount = 0;

        foreach ($unclosedKlines as $kline) {
            $this->klineRepository->markAsClosed($kline);

            // Broadcast the closed kline so clients know it's finalized
            event(new KlineUpdated($kline));

            Log::info("🔒 Closed kline and broadcasted", [
                'symbol' => $kline->symbol,
                'interval' => $kline->interval,
                'open_time' => $kline->open_time,
                'close_time' => $kline->close_time,
                'now' => $now,
            ]);

            $closedCount++;
        }

        return $closedCount;
    }

    /**
     * Get klines for API response.
     */
    public function getKlines(
        string $symbol,
        string $interval,
        ?int $startTime = null,
        ?int $endTime = null,
        int $limit = 500
    ) {
        // Validate interval
        $this->getIntervalSeconds($interval);

        if (! $startTime) {
            // Default: last 500 klines
            return $this->klineRepository->getRecentKlines($symbol, $interval, $limit);
        }

        if (! $endTime) {
            $endTime = now()->timestamp;
        }

        return $this->klineRepository->getKlines($symbol, $interval, $startTime, $endTime, $limit);
    }

    /**
     * Fill gaps in klines (if no trades occurred in an interval).
     *
     * Algorithm from guide:
     * If no trade in interval:
     *   open = close of previous candle
     *   high = open
     *   low = open
     *   close = open
     *   volume = 0
     */
    public function fillGaps(string $symbol, string $interval, int $startTime, int $endTime): int
    {
        $intervalSeconds = $this->getIntervalSeconds($interval);
        $filled = 0;

        $currentBucket = $this->calculateBucketTime($startTime, $intervalSeconds);

        while ($currentBucket <= $endTime) {
            $kline = $this->klineRepository->findByBucket($symbol, $interval, $currentBucket);

            if (! $kline) {
                // No kline exists for this bucket, get previous kline's close
                $previousBucket = $currentBucket - $intervalSeconds;
                $previousKline = $this->klineRepository->findByBucket($symbol, $interval, $previousBucket);

                $price = $previousKline ? $previousKline->close : '0';

                // Create empty kline with all OHLC = previous close
                $this->klineRepository->create([
                    'symbol' => $symbol,
                    'interval' => $interval,
                    'open_time' => $currentBucket,
                    'close_time' => $currentBucket + $intervalSeconds - 1,
                    'open' => $price,
                    'high' => $price,
                    'low' => $price,
                    'close' => $price,
                    'volume' => '0',
                    'quote_volume' => '0',
                    'trades_count' => 0,
                    'is_closed' => true, // Mark as closed since it's historical
                ]);

                $filled++;

                Log::debug("Filled gap for kline", [
                    'symbol' => $symbol,
                    'interval' => $interval,
                    'open_time' => $currentBucket,
                ]);
            }

            $currentBucket += $intervalSeconds;
        }

        return $filled;
    }
}
