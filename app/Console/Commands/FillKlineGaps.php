<?php

namespace App\Console\Commands;

use App\Services\KlineService;
use Illuminate\Console\Command;

class FillKlineGaps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'klines:fill-gaps
                            {symbol : Trading pair symbol (e.g., BTC/USDT)}
                            {interval : Kline interval (e.g., 1m, 1h, 1d)}
                            {--start= : Start time (Unix timestamp)}
                            {--end= : End time (Unix timestamp)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fill gaps in klines where no trades occurred';

    /**
     * Execute the console command.
     */
    public function handle(KlineService $klineService): int
    {
        $symbol = $this->argument('symbol');
        $interval = $this->argument('interval');
        $startTime = $this->option('start') ?? now()->subDays(7)->timestamp;
        $endTime = $this->option('end') ?? now()->timestamp;

        $this->info("Filling gaps for {$symbol} ({$interval})...");
        $this->info("Time range: {$startTime} to {$endTime}");

        try {
            $filled = $klineService->fillGaps($symbol, $interval, (int) $startTime, (int) $endTime);

            $this->info("Filled {$filled} gap(s).");

            return self::SUCCESS;
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
