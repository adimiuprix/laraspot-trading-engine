<?php

namespace App\Listeners;

use App\Events\TradeExecuted;
use App\Services\KlineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Listen to TradeExecuted event and update OHLCV klines for all intervals.
 *
 * This listener implements ShouldQueue to process kline updates asynchronously,
 * preventing blocking of the main trading flow.
 */
class UpdateKlineOnTradeExecuted implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 5;

    /**
     * Create the event listener.
     */
    public function __construct(
        private KlineService $klineService
    ) {}

    /**
     * Handle the event.
     *
     * This method is called automatically when TradeExecuted event is fired.
     * It updates klines for all configured intervals (1m, 5m, 1h, 1d, etc).
     */
    public function handle(TradeExecuted $event): void
    {
        try {
            $trade = $event->trade;

            Log::info('Processing kline update from trade', [
                'trade_id' => $trade->id,
                'symbol' => $trade->buyerOrder->symbol,
                'price' => $trade->price,
                'amount' => $trade->amount,
            ]);

            // Update klines for all intervals
            $this->klineService->updateAllIntervalsFromTrade($trade);

            Log::info('Kline update completed', [
                'trade_id' => $trade->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update klines from trade', [
                'trade_id' => $event->trade->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(TradeExecuted $event, \Throwable $exception): void
    {
        Log::critical('Kline update failed after all retries', [
            'trade_id' => $event->trade->id ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}
