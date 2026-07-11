<?php

namespace App\Console\Commands;

use App\Services\KlineService;
use Illuminate\Console\Command;

class CloseExpiredKlines extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'klines:close-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Close klines that have passed their close_time and broadcast updates';

    /**
     * Execute the console command.
     */
    public function handle(KlineService $klineService): int
    {
        $this->info('🔍 Checking for expired klines...');

        $closedCount = $klineService->closeExpiredKlines();

        if ($closedCount > 0) {
            $this->info("✅ Closed and broadcasted {$closedCount} kline(s)");
        } else {
            $this->info('✨ No expired klines found');
        }

        return self::SUCCESS;
    }
}
