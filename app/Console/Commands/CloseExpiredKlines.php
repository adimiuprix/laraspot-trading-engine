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
    protected $description = 'Close klines that have passed their close_time';

    /**
     * Execute the console command.
     */
    public function handle(KlineService $klineService): int
    {
        $this->info('Closing expired klines...');

        $closedCount = $klineService->closeExpiredKlines();

        $this->info("Closed {$closedCount} expired klines.");

        return self::SUCCESS;
    }
}
